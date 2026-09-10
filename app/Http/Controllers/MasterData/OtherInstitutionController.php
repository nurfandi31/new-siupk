<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Services\MasterDataCsvService;
use App\Http\Requests\MasterData\OtherInstitutionRequest;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\Services\TenantSequenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OtherInstitutionController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'institutions.view');

        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = $this->sort((string) $request->query('sort', 'name'));
        $direction = $this->direction((string) $request->query('direction', 'asc'));
        $institutions = OrganizationUnit::query()
            ->otherInstitutions()
            ->with('parent:row_id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('institution_identity_number', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (OrganizationUnit $institution): array => [
                ...$institution->only(['row_id', 'code', 'name', 'address', 'phone', 'institution_identity_number', 'leader_name', 'responsible_name', 'is_active']),
                'village' => $institution->parent?->only(['row_id', 'name']),
            ]);

        return Inertia::render('MasterData/Institutions/Index', [
            'institutions' => $institutions,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'institutions.manage');

        return Inertia::render('MasterData/Institutions/Form', [
            'institution' => null,
            'villages' => $this->activeVillages(),
        ]);
    }

    public function store(OtherInstitutionRequest $request, TenantSequenceService $sequences): RedirectResponse
    {
        $attributes = $request->validated();

        foreach (range(1, 3) as $attempt) {
            try {
                DB::connection('tenant')->transaction(fn () => OrganizationUnit::query()->create([
                    ...$attributes,
                    'id' => $sequences->next('organization_units'),
                    'code' => $this->randomCode(),
                    'type' => 'other_institution',
                ]));
                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 3 || ! str_contains($exception->getMessage(), 'uq_org_units_code')) {
                    throw $exception;
                }
            }
        }

        return to_route('master-data.institutions.index')->with('success', 'Lembaga berhasil ditambahkan.');
    }

    public function show(Request $request, OrganizationUnit $institution): Response
    {
        $this->permissions->denyUnless($request->user(), 'institutions.view');
        abort_unless($institution->type === 'other_institution', 404);
        $institution->load('parent:row_id,name,code');

        // Lembaga lain belum punya relasi pinjaman di schema V1 — riwayat kosong, UI tetap konsisten.
        return Inertia::render('MasterData/Institutions/Show', [
            'institution' => [
                ...$institution->only([
                    'row_id', 'code', 'name', 'address', 'phone',
                    'institution_identity_number', 'leader_name', 'responsible_name', 'is_active',
                ]),
                'village' => $institution->parent?->only(['row_id', 'name', 'code']),
            ],
            'loans' => [],
            'summary' => [
                'loan_count' => 0,
                'active_loan_count' => 0,
                'principal_remaining' => 0.0,
            ],
            'loan_note' => 'Riwayat pinjaman lembaga belum terhubung di skema saat ini. Relasi pinjaman lembaga akan ditambahkan bila produk mendukung.',
        ]);
    }

    public function edit(Request $request, OrganizationUnit $institution): Response
    {
        $this->permissions->denyUnless($request->user(), 'institutions.manage');
        abort_unless($institution->type === 'other_institution', 404);

        return Inertia::render('MasterData/Institutions/Form', [
            'institution' => $institution->only([
                'row_id', 'parent_row_id', 'code', 'name', 'address', 'phone',
                'institution_identity_number', 'leader_name', 'responsible_name', 'is_active',
            ]),
            'villages' => $this->activeVillages(),
        ]);
    }

    public function update(OtherInstitutionRequest $request, OrganizationUnit $institution): RedirectResponse
    {
        abort_unless($institution->type === 'other_institution', 404);
        $institution->update($request->validated());

        return to_route('master-data.institutions.index')->with('success', 'Lembaga berhasil diperbarui.');
    }

    public function export(Request $request, MasterDataCsvService $csv): StreamedResponse
    {
        $this->permissions->denyUnless($request->user(), 'institutions.manage');

        return $csv->exportInstitutions();
    }

    public function import(Request $request, MasterDataCsvService $csv): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'institutions.manage');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [], ['file' => 'file CSV']);

        $result = $csv->importInstitutions($request->file('file'));
        $message = sprintf(
            'Impor lembaga selesai. Berhasil: %d, dilewati: %d.',
            $result['imported'],
            $result['skipped'],
        );

        if ($result['errors'] !== []) {
            $preview = implode(' ', array_slice($result['errors'], 0, 3));
            $extra = count($result['errors']) > 3 ? ' …' : '';

            return to_route('master-data.institutions.index')->with('warning', $message.' Error: '.$preview.$extra);
        }

        return to_route('master-data.institutions.index')->with('success', $message);
    }

    private function activeVillages(): array
    {
        return OrganizationUnit::query()->villages()->active()->orderBy('name')->get(['row_id', 'name'])->toArray();
    }

    private function randomCode(): string
    {
        $code = '';

        foreach (range(1, 14) as $_) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [15, 30, 50, 100], true) ? $value : 15;
    }

    private function sort(string $value): string
    {
        return [
            'code' => 'code',
            'name' => 'name',
            'institution_identity_number' => 'institution_identity_number',
            'leader_name' => 'leader_name',
        ][$value] ?? 'name';
    }

    private function direction(string $value): string
    {
        return in_array($value, ['asc', 'desc'], true) ? $value : 'asc';
    }
}
