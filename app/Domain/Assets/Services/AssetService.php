<?php

declare(strict_types=1);

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetCategory;
use App\Domain\Assets\Models\AssetStatusHistory;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Inventaris tenant: register + status + nilai buku (garis lurus).
 * Jurnal pembelian/penyusutan tetap lewat jurnal umum (COA 1.2.x / 5.1.07).
 */
final class AssetService
{
    private const DEFAULT_CATEGORIES = [
        ['code' => 'TANAH', 'name' => 'Tanah', 'months' => null],
        ['code' => 'BANGUNAN', 'name' => 'Bangunan', 'months' => 240],
        ['code' => 'KENDARAAN', 'name' => 'Kendaraan', 'months' => 60],
        ['code' => 'PERALATAN', 'name' => 'Peralatan & Mesin', 'months' => 48],
        ['code' => 'ATB', 'name' => 'Aset Tak Berwujud', 'months' => 60],
    ];

    /**
     * @return array{
     *   assets: LengthAwarePaginator,
     *   filters: array{q:?string,status:?string,category:?int},
     *   categories: list<array{value:int,label:string}>,
     *   status_options: list<array{value:string,label:string}>,
     *   counts: array{total:int,good:int,acquisition:float,book:float}
     * }
     */
    public function index(?string $q, ?string $status, ?int $categoryId, int $perPage = 15, string $asOf = ''): array
    {
        $this->ensureDefaultCategories();
        $asOfDate = $this->parseDate($asOf) ?? CarbonImmutable::today();

        $query = Asset::query()
            ->with(['category:row_id,code,name', 'unit:row_id,name,code'])
            ->orderByDesc('purchased_at')
            ->orderBy('name');

        if (is_string($q) && trim($q) !== '') {
            $term = '%'.trim($q).'%';
            $query->where(function ($w) use ($term): void {
                $w->where('name', 'like', $term)
                    ->orWhere('asset_code', 'like', $term);
            });
        }

        if (is_string($status) && $status !== '' && $status !== 'all' && isset(Asset::STATUSES[$status])) {
            $query->where('status', $status);
        }

        if ($categoryId !== null && $categoryId > 0) {
            $query->where('asset_category_row_id', $categoryId);
        }

        $paginator = $query
            ->paginate(max(10, min(100, $perPage)))
            ->withQueryString()
            ->through(fn (Asset $a): array => $this->row($a, $asOfDate));

        $all = Asset::query()->get();
        $acquisition = 0.0;
        $book = 0.0;
        $good = 0;
        foreach ($all as $asset) {
            $calc = $this->bookValue($asset, $asOfDate);
            $acquisition += $calc['acquisition'];
            if (in_array((string) $asset->status, ['good', 'damaged'], true)) {
                $book += $calc['book_value'];
            }
            if ($asset->status === 'good') {
                $good++;
            }
        }

        return [
            'assets' => $paginator,
            'filters' => [
                'q' => is_string($q) && trim($q) !== '' ? trim($q) : null,
                'status' => is_string($status) && $status !== '' && $status !== 'all' ? $status : null,
                'category' => $categoryId,
                'as_of' => $asOfDate->toDateString(),
            ],
            'categories' => $this->categoryOptions(),
            'status_options' => $this->statusOptions(),
            'counts' => [
                'total' => $all->count(),
                'good' => $good,
                'acquisition' => round($acquisition, 2),
                'book' => round($book, 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): Asset
    {
        return DB::connection('tenant')->transaction(function () use ($data, $userId): Asset {
            $this->ensureDefaultCategories();

            $categoryCode = isset($data['category_code']) && is_string($data['category_code']) && $data['category_code'] !== ''
                ? $data['category_code']
                : null;
            $categoryId = $categoryCode !== null
                ? AssetCategory::query()->where('code', $categoryCode)->value('row_id')
                : null;
            if ($categoryId === null && isset($data['asset_category_row_id']) && (int) $data['asset_category_row_id'] > 0) {
                $categoryId = (int) $data['asset_category_row_id'];
            }

            $asset = Asset::query()->create([
                'organization_unit_row_id' => $data['organization_unit_row_id'] ?? null,
                'asset_category_row_id' => $categoryId ?? ($data['asset_category_row_id'] ?? null),
                'asset_code' => $data['asset_code'] ?? null,
                'name' => $data['name'],
                'purchased_at' => $data['purchased_at'] ?? null,
                'quantity' => (int) ($data['quantity'] ?? 1),
                'unit_cost' => (float) ($data['unit_cost'] ?? 0),
                'useful_life_months' => $data['useful_life_months'] ?? null,
                'status' => $data['status'] ?? 'good',
                'validated_at' => $data['validated_at'] ?? null,
            ]);

            $this->recordStatus(
                $asset,
                from: null,
                to: (string) $asset->status,
                notes: 'Registrasi inventaris',
                userId: $userId,
            );

            return $asset->load(['category', 'unit']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Asset $asset, array $data, int $userId): Asset
    {
        return DB::connection('tenant')->transaction(function () use ($asset, $data, $userId): Asset {
            $fromStatus = (string) $asset->status;
            $toStatus = (string) ($data['status'] ?? $fromStatus);

            $asset->update([
                'organization_unit_row_id' => $data['organization_unit_row_id'] ?? null,
                'asset_category_row_id' => $data['asset_category_row_id'] ?? null,
                'asset_code' => $data['asset_code'] ?? null,
                'name' => $data['name'],
                'purchased_at' => $data['purchased_at'] ?? null,
                'quantity' => (int) ($data['quantity'] ?? 1),
                'unit_cost' => (float) ($data['unit_cost'] ?? 0),
                'useful_life_months' => $data['useful_life_months'] ?? null,
                'status' => $toStatus,
                'validated_at' => $data['validated_at'] ?? null,
            ]);

            if ($fromStatus !== $toStatus) {
                $this->recordStatus(
                    $asset,
                    from: $fromStatus,
                    to: $toStatus,
                    notes: $data['status_notes'] ?? null,
                    userId: $userId,
                );
            }

            return $asset->fresh()->load(['category', 'unit']);
        });
    }

    public function delete(Asset $asset): void
    {
        $asset->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Asset $asset, ?string $asOf = null): array
    {
        $asOfDate = $this->parseDate($asOf) ?? CarbonImmutable::today();
        $asset->load(['category', 'unit', 'statusHistories' => fn ($q) => $q->orderByDesc('changed_at')->limit(20)]);

        return [
            'asset' => $this->row($asset, $asOfDate),
            'histories' => $asset->statusHistories->map(fn (AssetStatusHistory $h): array => [
                'row_id' => (int) $h->row_id,
                'from_status' => $h->from_status,
                'from_label' => $h->from_status ? (Asset::STATUSES[$h->from_status] ?? $h->from_status) : null,
                'to_status' => $h->to_status,
                'to_label' => Asset::STATUSES[$h->to_status] ?? $h->to_status,
                'notes' => $h->notes,
                'changed_at' => $h->changed_at?->format('Y-m-d H:i'),
            ])->all(),
            'as_of' => $asOfDate->toDateString(),
            'categories' => $this->categoryOptions(),
            'status_options' => $this->statusOptions(),
        ];
    }

    /**
     * @return array{
     *   acquisition: float,
     *   monthly_depreciation: float,
     *   age_months: int,
     *   accumulated_depreciation: float,
     *   book_value: float,
     *   fully_depreciated: bool
     * }
     */
    public function bookValue(Asset $asset, CarbonImmutable $asOf): array
    {
        $qty = max(1, (int) $asset->quantity);
        $unit = (float) $asset->unit_cost;
        $acquisition = round($qty * $unit, 2);
        $life = $asset->useful_life_months !== null ? (int) $asset->useful_life_months : 0;

        // Tanah / tanpa umur ekonomis: tidak disusutkan.
        if ($life <= 0 || $asset->purchased_at === null) {
            return [
                'acquisition' => $acquisition,
                'monthly_depreciation' => 0.0,
                'age_months' => 0,
                'accumulated_depreciation' => 0.0,
                'book_value' => $acquisition,
                'fully_depreciated' => false,
            ];
        }

        $start = CarbonImmutable::parse($asset->purchased_at->format('Y-m-d'))->startOfMonth();
        $end = $asOf->startOfMonth();
        $age = 0;
        if ($end->greaterThanOrEqualTo($start)) {
            $age = ($end->year - $start->year) * 12 + ($end->month - $start->month);
            // hitung bulan berjalan penuh sejak beli: +1 seperti legacy "bulan kepemilikan"
            $age = max(0, $age + 1);
        }

        $monthly = round($acquisition / $life, 2);
        $cappedAge = min($age, $life);
        $accum = round($monthly * $cappedAge, 2);
        if ($accum > $acquisition) {
            $accum = $acquisition;
        }
        $book = round($acquisition - $accum, 2);
        // Residual minimal 0 (bukan 1 legacy quirk).
        if ($book < 0) {
            $book = 0.0;
        }

        return [
            'acquisition' => $acquisition,
            'monthly_depreciation' => $monthly,
            'age_months' => $age,
            'accumulated_depreciation' => $accum,
            'book_value' => $book,
            'fully_depreciated' => $cappedAge >= $life,
        ];
    }

    /**
     * @return list<array{value:int,label:string,default_useful_life_months:?int}>
     */
    public function categoryOptions(): array
    {
        $this->ensureDefaultCategories();

        return AssetCategory::query()
            ->orderBy('name')
            ->get()
            ->map(fn (AssetCategory $c): array => [
                'value' => (int) $c->row_id,
                'label' => $c->name.($c->code ? " ({$c->code})" : ''),
                'default_useful_life_months' => $c->default_useful_life_months,
            ])
            ->all();
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public function statusOptions(): array
    {
        $out = [['value' => 'all', 'label' => 'Semua status']];
        foreach (Asset::STATUSES as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    public function ensureDefaultCategories(): void
    {
        if (AssetCategory::query()->exists()) {
            return;
        }

        foreach (self::DEFAULT_CATEGORIES as $row) {
            AssetCategory::query()->create([
                'code' => $row['code'],
                'name' => $row['name'],
                'default_useful_life_months' => $row['months'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Asset $asset, CarbonImmutable $asOf): array
    {
        $calc = $this->bookValue($asset, $asOf);

        return [
            'row_id' => (int) $asset->row_id,
            'id' => (int) $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'purchased_at' => $asset->purchased_at?->format('Y-m-d'),
            'quantity' => (int) $asset->quantity,
            'unit_cost' => (float) $asset->unit_cost,
            'useful_life_months' => $asset->useful_life_months !== null ? (int) $asset->useful_life_months : null,
            'status' => (string) $asset->status,
            'status_label' => Asset::STATUSES[(string) $asset->status] ?? (string) $asset->status,
            'validated_at' => $asset->validated_at?->format('Y-m-d'),
            'organization_unit_row_id' => $asset->organization_unit_row_id !== null ? (int) $asset->organization_unit_row_id : null,
            'asset_category_row_id' => $asset->asset_category_row_id !== null ? (int) $asset->asset_category_row_id : null,
            'category' => $asset->category ? [
                'row_id' => (int) $asset->category->row_id,
                'code' => $asset->category->code,
                'name' => $asset->category->name,
            ] : null,
            'unit' => $asset->unit ? [
                'row_id' => (int) $asset->unit->row_id,
                'name' => $asset->unit->name,
                'code' => $asset->unit->code,
            ] : null,
            'acquisition' => $calc['acquisition'],
            'monthly_depreciation' => $calc['monthly_depreciation'],
            'age_months' => $calc['age_months'],
            'accumulated_depreciation' => $calc['accumulated_depreciation'],
            'book_value' => $calc['book_value'],
            'fully_depreciated' => $calc['fully_depreciated'],
        ];
    }

    private function recordStatus(Asset $asset, ?string $from, string $to, ?string $notes, int $userId): void
    {
        if (! isset(Asset::STATUSES[$to])) {
            throw new DomainException("Status inventaris tidak valid: {$to}");
        }

        AssetStatusHistory::query()->create([
            'asset_row_id' => $asset->row_id,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'changed_at' => now(),
            'changed_by_user_id' => $userId,
        ]);
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
