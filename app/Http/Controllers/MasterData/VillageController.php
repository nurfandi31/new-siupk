<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Domain\Access\Services\PermissionChecker;
use App\Http\Requests\MasterData\VillageRequest;
use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\VillageNaming;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VillageController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'villages.view');

        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = $this->sort((string) $request->query('sort', 'name'));
        $direction = $this->direction((string) $request->query('direction', 'asc'));
        $villages = OrganizationUnit::query()->villages()
            ->with('villageNaming:row_id,village_name,village_head_name')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (OrganizationUnit $village): array => [
                ...$village->only([
                    'row_id', 'code', 'name', 'address', 'phone', 'is_active',
                    'village_head_name', 'village_head_phone', 'village_head_nip',
                    'village_secretary_name', 'village_secretary_phone',
                    'village_council_name', 'installment_schedule', 'village_naming_id',
                ]),
                'village_naming' => $village->villageNaming?->only(['village_name', 'village_head_name']),
            ]);

        return Inertia::render('MasterData/Villages/Index', [
            'villages' => $villages,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function edit(Request $request, OrganizationUnit $village): Response
    {
        $this->permissions->denyUnless($request->user(), 'villages.manage');
        abort_unless($village->type === 'village', 404);

        return Inertia::render('MasterData/Villages/Form', [
            'village' => $village->only([
                'row_id', 'code', 'name', 'address', 'phone', 'is_active',
                'village_head_name', 'village_head_phone', 'village_head_nip',
                'village_secretary_name', 'village_secretary_phone',
                'village_council_name', 'installment_schedule', 'village_naming_id',
            ]),
            'villageNamings' => VillageNaming::query()->active()
                ->orderBy('village_name')
                ->get(['row_id', 'village_name', 'village_head_name']),
        ]);
    }

    public function update(VillageRequest $request, OrganizationUnit $village): RedirectResponse
    {
        abort_unless($village->type === 'village', 404);
        $village->update($request->validated());

        return to_route('master-data.villages.index')->with('success', 'Desa berhasil diperbarui.');
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
            'address' => 'address',
            'village_head_name' => 'village_head_name',
        ][$value] ?? 'name';
    }

    private function direction(string $value): string
    {
        return in_array($value, ['asc', 'desc'], true) ? $value : 'asc';
    }
}
