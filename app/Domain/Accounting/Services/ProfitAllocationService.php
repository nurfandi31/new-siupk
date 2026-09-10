<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alokasi laba (legacy step 2, simplified):
 *   Dr 3.2.02.01 Laba/Rugi Tahun Berjalan
 *   Cr 2.1.04.01 Utang Laba Bagian Masyarakat  (opsional sub-baris deskripsi)
 *   Cr 2.1.04.02 Utang Laba Bagian Desa         (opsional per desa + org unit)
 *   Cr 2.1.04.03 Utang Laba Bagian Penyerta Modal
 *   Cr 3.2.01.01 Laba Ditahan
 *
 * Σ kredit = debit ≤ sisa laba berjalan (signed C-normal).
 * source_type=profit_allocation, source_row_id=tahun sumber laba.
 */
final class ProfitAllocationService
{
    public const SOURCE = 'profit_allocation';

    public const EARNINGS_CODE = '3.2.02.01';

    public const RETAINED_CODE = '3.2.01.01';

    public const COMMUNITY_CODE = '2.1.04.01';

    public const VILLAGE_CODE = '2.1.04.02';

    public const INVESTOR_CODE = '2.1.04.03';

    /** @var list<string> */
    public const COMMUNITY_LABELS = [
        'sosial' => 'Kegiatan sosial kemasyarakatan dan bantuan RTM',
        'kapasitas' => 'Pengembangan kapasitas kelompok SPP/UEP',
        'pelatihan' => 'Pelatihan masyarakat dan kelompok pemanfaat umum',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly AccountBalanceQuery $balances,
        private readonly JournalPostingService $posting,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function formState(int $year): array
    {
        $this->assertYear($year);
        $accounts = $this->resolveAccounts();
        $asOf = CarbonImmutable::create($year, 12, 31)->startOfDay();
        // Prefer live NI for the profit year; if next-year openings already carry earnings, use that too.
        $available = $this->availableEarnings($year, $accounts['earnings']);

        $existing = JournalEntry::query()
            ->where('source_type', self::SOURCE)
            ->where('source_row_id', $year)
            ->where('status', 'posted')
            ->orderByDesc('row_id')
            ->get(['row_id', 'id', 'transaction_date', 'description', 'journal_number']);

        $allocated = 0.0;
        foreach ($existing as $je) {
            $sum = DB::connection('tenant')
                ->table('journal_lines')
                ->where('tenant_id', $this->context->id())
                ->where('journal_entry_row_id', $je->row_id)
                ->where('account_row_id', $accounts['earnings']->row_id)
                ->sum('debit');
            $allocated = round($allocated + (float) $sum, 2);
        }

        $villages = OrganizationUnit::query()
            ->villages()
            ->active()
            ->orderBy('name')
            ->get(['row_id', 'code', 'name'])
            ->map(fn (OrganizationUnit $v) => [
                'row_id' => (int) $v->row_id,
                'code' => (string) $v->code,
                'name' => (string) $v->name,
            ])
            ->values()
            ->all();

        $communityLines = [];
        foreach (self::COMMUNITY_LABELS as $key => $label) {
            $communityLines[] = ['key' => $key, 'label' => $label, 'amount' => 0];
        }

        return [
            'profit_year' => $year,
            'available' => $available,
            'already_allocated' => $allocated,
            'remaining' => round(max(0, $available - $allocated), 2),
            'accounts' => [
                'earnings' => $this->accountPayload($accounts['earnings']),
                'retained' => $this->accountPayload($accounts['retained']),
                'community' => $this->accountPayload($accounts['community']),
                'village' => $this->accountPayload($accounts['village']),
                'investor' => $this->accountPayload($accounts['investor']),
            ],
            'community_lines' => $communityLines,
            'villages' => $villages,
            'existing' => $existing->map(fn (JournalEntry $e) => [
                'row_id' => (int) $e->row_id,
                'id' => (int) $e->id,
                'transaction_date' => $e->transaction_date?->format('Y-m-d'),
                'description' => $e->description,
                'href' => '/accounting/journals?q='.urlencode((string) $e->id),
            ])->values()->all(),
            'default_date' => CarbonImmutable::create($year + 1, 1, 1)->toDateString(),
        ];
    }

    /**
     * @param  array{
     *   date: string,
     *   community?: array<string, float|int|string>,
     *   villages?: array<int|string, float|int|string>,
     *   investor?: float|int|string,
     *   retained?: float|int|string,
     *   note?: string|null
     * }  $input
     * @return array{journal_row_id: int, journal_id: int, total: float}
     */
    public function allocate(int $year, array $input, int $userId): array
    {
        $this->assertYear($year);
        $accounts = $this->resolveAccounts();
        $date = CarbonImmutable::parse((string) $input['date'])->startOfDay();

        $period = FiscalPeriod::query()
            ->whereDate('starts_at', '<=', $date->toDateString())
            ->whereDate('ends_at', '>=', $date->toDateString())
            ->first();
        if ($period === null) {
            throw new DomainException('Tidak ada periode fiskal untuk tanggal alokasi. Buat periode dulu.');
        }
        if ($period->status !== 'open') {
            throw new DomainException('Periode fiskal tanggal alokasi sudah ditutup. Buka periode atau pilih tanggal lain.');
        }

        $communityParts = [];
        foreach (self::COMMUNITY_LABELS as $key => $label) {
            $amt = $this->money($input['community'][$key] ?? 0);
            if ($amt > 0) {
                $communityParts[] = ['key' => $key, 'label' => $label, 'amount' => $amt];
            }
        }
        $communityTotal = round(array_sum(array_column($communityParts, 'amount')), 2);

        $villageParts = [];
        $villageInput = is_array($input['villages'] ?? null) ? $input['villages'] : [];
        foreach ($villageInput as $villageRowId => $raw) {
            $amt = $this->money($raw);
            if ($amt <= 0) {
                continue;
            }
            $vid = (int) $villageRowId;
            $village = OrganizationUnit::query()->villages()->active()->whereKey($vid)->first();
            if ($village === null) {
                throw new DomainException("Desa row_id {$vid} tidak valid.");
            }
            $villageParts[] = [
                'row_id' => $vid,
                'name' => (string) $village->name,
                'amount' => $amt,
            ];
        }
        $villageTotal = round(array_sum(array_column($villageParts, 'amount')), 2);

        $investor = $this->money($input['investor'] ?? 0);
        $retained = $this->money($input['retained'] ?? 0);
        $total = round($communityTotal + $villageTotal + $investor + $retained, 2);

        if ($total <= 0) {
            throw new DomainException('Isi minimal satu pos alokasi > 0.');
        }

        $available = $this->availableEarnings($year, $accounts['earnings']);
        $already = $this->alreadyAllocated($year, (int) $accounts['earnings']->row_id);
        $remaining = round($available - $already, 2);
        if (bccomp((string) $total, (string) $remaining, 2) === 1) {
            throw new DomainException(sprintf(
                'Total alokasi %s melebihi sisa laba %s.',
                number_format($total, 2, ',', '.'),
                number_format($remaining, 2, ',', '.'),
            ));
        }

        $note = trim((string) ($input['note'] ?? ''));
        $description = $note !== ''
            ? $note
            : sprintf('Alokasi laba tahun %d', $year);

        $entry = DB::connection('tenant')->transaction(function () use (
            $year,
            $date,
            $userId,
            $accounts,
            $communityParts,
            $villageParts,
            $investor,
            $retained,
            $total,
            $description,
        ): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $date->toDateString(),
                'sequence_number' => 0,
                'source_type' => self::SOURCE,
                'source_row_id' => $year,
                'description' => $description,
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $line = 1;
            // Debit earnings
            JournalLine::query()->create([
                'journal_entry_row_id' => $entry->row_id,
                'line_number' => $line++,
                'account_row_id' => $accounts['earnings']->row_id,
                'organization_unit_row_id' => null,
                'description' => $description,
                'debit' => $total,
                'credit' => 0,
            ]);

            foreach ($communityParts as $part) {
                JournalLine::query()->create([
                    'journal_entry_row_id' => $entry->row_id,
                    'line_number' => $line++,
                    'account_row_id' => $accounts['community']->row_id,
                    'organization_unit_row_id' => null,
                    'description' => $part['label'].' tahun '.$year,
                    'debit' => 0,
                    'credit' => $part['amount'],
                ]);
            }

            foreach ($villageParts as $part) {
                JournalLine::query()->create([
                    'journal_entry_row_id' => $entry->row_id,
                    'line_number' => $line++,
                    'account_row_id' => $accounts['village']->row_id,
                    'organization_unit_row_id' => $part['row_id'],
                    'description' => 'Alokasi laba bagian desa '.$part['name'].' tahun '.$year,
                    'debit' => 0,
                    'credit' => $part['amount'],
                ]);
            }

            if ($investor > 0) {
                JournalLine::query()->create([
                    'journal_entry_row_id' => $entry->row_id,
                    'line_number' => $line++,
                    'account_row_id' => $accounts['investor']->row_id,
                    'organization_unit_row_id' => null,
                    'description' => 'Laba bagian penyerta modal tahun '.$year,
                    'debit' => 0,
                    'credit' => $investor,
                ]);
            }

            if ($retained > 0) {
                JournalLine::query()->create([
                    'journal_entry_row_id' => $entry->row_id,
                    'line_number' => $line++,
                    'account_row_id' => $accounts['retained']->row_id,
                    'organization_unit_row_id' => null,
                    'description' => 'Laba ditahan tahun '.$year,
                    'debit' => 0,
                    'credit' => $retained,
                ]);
            }

            return $entry;
        }, 5);

        $posted = $this->posting->post($entry, $userId);

        return [
            'journal_row_id' => (int) $posted->row_id,
            'journal_id' => (int) $posted->id,
            'total' => $total,
        ];
    }

    /**
     * @return array{
     *   earnings: Account,
     *   retained: Account,
     *   community: Account,
     *   village: Account,
     *   investor: Account
     * }
     */
    private function resolveAccounts(): array
    {
        $codes = [
            'earnings' => self::EARNINGS_CODE,
            'retained' => self::RETAINED_CODE,
            'community' => self::COMMUNITY_CODE,
            'village' => self::VILLAGE_CODE,
            'investor' => self::INVESTOR_CODE,
        ];
        $out = [];
        foreach ($codes as $key => $code) {
            $account = Account::query()
                ->where('code', $code)
                ->where('is_postable', true)
                ->where('is_active', true)
                ->first();
            if ($account === null) {
                throw new DomainException("Akun {$code} tidak ditemukan / nonaktif. Periksa COA.");
            }
            $out[$key] = $account;
        }

        return $out;
    }

    private function availableEarnings(int $year, Account $earnings): float
    {
        // Net income of profit year (from P/L accounts) — source of truth for "berapa laba".
        $ni = $this->balances->netIncome(CarbonImmutable::create($year, 12, 31)->startOfDay());

        // Also consider current signed balance on earnings account as-of today (after prior allocations).
        // For form "available before any allocation this session", use NI; remaining = NI - already allocated.
        return round(max(0, $ni), 2);
    }

    private function alreadyAllocated(int $year, int $earningsRowId): float
    {
        $ids = JournalEntry::query()
            ->where('source_type', self::SOURCE)
            ->where('source_row_id', $year)
            ->where('status', 'posted')
            ->pluck('row_id');

        if ($ids->isEmpty()) {
            return 0.0;
        }

        return round((float) DB::connection('tenant')
            ->table('journal_lines')
            ->where('tenant_id', $this->context->id())
            ->whereIn('journal_entry_row_id', $ids->all())
            ->where('account_row_id', $earningsRowId)
            ->sum('debit'), 2);
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(['.', ' '], ['', ''], $value);
            $value = str_replace(',', '.', $value);
        }
        $n = round((float) $value, 2);

        return $n > 0 ? $n : 0.0;
    }

    /**
     * @return array{row_id: int, code: string, name: string}
     */
    private function accountPayload(Account $account): array
    {
        return [
            'row_id' => (int) $account->row_id,
            'code' => (string) $account->code,
            'name' => (string) $account->name,
        ];
    }

    private function assertYear(int $year): void
    {
        if ($year < 2000 || $year > 2100) {
            throw new DomainException('Tahun tidak valid.');
        }
    }
}
