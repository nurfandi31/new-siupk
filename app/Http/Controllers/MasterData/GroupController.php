<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\EntityLoanHistoryService;
use App\Domain\Membership\Services\GroupService;
use App\Domain\Membership\Services\MasterDataCsvService;
use App\Domain\Membership\Services\MemberService;
use App\Http\Requests\MasterData\GroupRequest;
use App\Http\Requests\MasterData\QuickMemberRequest;
use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GroupController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = $this->sort((string) $request->query('sort', 'name'));
        $direction = $this->direction((string) $request->query('direction', 'asc'));
        $groups = Group::query()
            ->with(['village:row_id,name', 'activeOfficers' => fn ($query) => $query->where('position', 'chair')->with('member.person')])
            ->withCount('activeMemberships')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhereHas('village', fn ($village) => $village->where('name', 'like', "%{$search}%"))))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Group $group): array => [
                ...$group->only(['row_id', 'id', 'code', 'name', 'established_at', 'status']),
                'established_at' => $group->established_at?->format('Y-m-d'),
                'village' => $group->village?->only(['row_id', 'id', 'name']),
                'members_count' => $group->active_memberships_count,
                'chair' => $group->activeOfficers->first()?->member?->person?->full_name,
            ]);

        return Inertia::render('MasterData/Groups/Index', compact('groups', 'search', 'perPage', 'sort', 'direction'));
    }

    public function create(TenantGroupMasterDataProvisioner $masterData): Response
    {
        $masterData->ensureDefaults();

        return Inertia::render('MasterData/Groups/Form', [
            'group' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(GroupRequest $request, GroupService $groups): RedirectResponse
    {
        $groups->create($request->validated());

        return to_route('master-data.groups.index')->with('success', 'Kelompok berhasil ditambahkan.');
    }

    public function show(Group $group, EntityLoanHistoryService $loanHistory): Response
    {
        $group->load([
            'village:row_id,name,code',
            'businessType:row_id,name',
            'activityType:row_id,name',
            'level:row_id,name',
            'functionType:row_id,name',
            'activeMemberships.member.person',
            'activeOfficers.member.person',
        ]);

        $loans = $loanHistory->forGroup((int) $group->row_id);
        $activeLoans = collect($loans)->whereIn('status', ['active', 'disbursed'])->count();
        $outstanding = collect($loans)->sum(fn (array $l): float => (float) ($l['principal_remaining'] ?? 0));

        $officers = [];
        foreach ($group->activeOfficers as $officer) {
            $officers[] = [
                'position' => (string) $officer->position,
                'member_row_id' => (int) $officer->member_row_id,
                'name' => $officer->member?->person?->full_name,
                'member_href' => $officer->member_row_id
                    ? '/master-data/members/'.$officer->member_row_id
                    : null,
            ];
        }

        $members = $group->activeMemberships->map(fn ($gm) => [
            'row_id' => (int) $gm->member_row_id,
            'member_number' => $gm->member?->member_number,
            'name' => $gm->member?->person?->full_name,
            'href' => '/master-data/members/'.$gm->member_row_id,
        ])->values()->all();

        return Inertia::render('MasterData/Groups/Show', [
            'group' => [
                'row_id' => (int) $group->row_id,
                'id' => (int) $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'address' => $group->address,
                'phone' => $group->phone,
                'status' => $group->status,
                'established_at' => $group->established_at?->format('Y-m-d'),
                'village' => $group->village?->only(['row_id', 'name', 'code']),
                'business_type' => $group->businessType?->name,
                'activity_type' => $group->activityType?->name,
                'level' => $group->level?->name,
                'function' => $group->functionType?->name,
                'officers' => $officers,
                'members' => $members,
                'members_count' => count($members),
            ],
            'loans' => $loans,
            'summary' => [
                'loan_count' => count($loans),
                'active_loan_count' => $activeLoans,
                'principal_remaining' => round((float) $outstanding, 2),
            ],
        ]);
    }

    public function edit(Group $group, TenantGroupMasterDataProvisioner $masterData): Response
    {
        $masterData->ensureDefaults();
        $group->load(['activeMemberships.member.person', 'activeOfficers.member.person']);

        return Inertia::render('MasterData/Groups/Form', [
            'group' => $this->formGroup($group),
            ...$this->formOptions(),
        ]);
    }

    public function update(GroupRequest $request, Group $group, GroupService $groups): RedirectResponse
    {
        $groups->update($group, $request->validated());

        return to_route('master-data.groups.index')->with('success', 'Kelompok berhasil diperbarui.');
    }

    public function export(MasterDataCsvService $csv): StreamedResponse
    {
        return $csv->exportGroups();
    }

    public function import(Request $request, MasterDataCsvService $csv): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [], ['file' => 'file CSV']);

        try {
            $result = $csv->importGroups($request->file('file'));
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = sprintf(
            'Impor kelompok selesai. Berhasil: %d, dilewati: %d.',
            $result['imported'],
            $result['skipped'],
        );

        if ($result['errors'] !== []) {
            $preview = implode(' ', array_slice($result['errors'], 0, 3));
            $extra = count($result['errors']) > 3 ? ' …' : '';

            return to_route('master-data.groups.index')->with('warning', $message.' Error: '.$preview.$extra);
        }

        return to_route('master-data.groups.index')->with('success', $message);
    }

    public function destroy(Request $request, Group $group, PermissionChecker $permissions): RedirectResponse
    {
        $permissions->denyUnless($request->user(), 'groups.manage');

        $tenantId = $group->tenant_id;
        $groupRowId = (int) $group->row_id;

        $hasLoans = DB::connection('tenant')->table('loan_borrowers')
            ->where('tenant_id', $tenantId)
            ->where('group_row_id', $groupRowId)
            ->exists();

        if ($hasLoans) {
            return back()->with('error', 'Kelompok tidak dapat dihapus karena memiliki riwayat pinjaman.');
        }

        $hasMembers = DB::connection('tenant')->table('group_members')
            ->where('tenant_id', $tenantId)
            ->where('group_row_id', $groupRowId)
            ->exists();

        if ($hasMembers) {
            return back()->with('error', 'Kelompok tidak dapat dihapus karena masih memiliki anggota.');
        }

        DB::connection('tenant')->transaction(function () use ($group, $tenantId, $groupRowId): void {
            DB::connection('tenant')->table('group_officers')
                ->where('tenant_id', $tenantId)
                ->where('group_row_id', $groupRowId)
                ->delete();

            $group->delete();
        });

        return to_route('master-data.groups.index')->with('success', 'Kelompok berhasil dihapus.');
    }

    public function memberOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $exclude = array_values(array_filter(array_map('intval', (array) $request->query('exclude', [])), fn ($id) => $id > 0));

        $members = Member::query()
            ->where('status', 'active')
            ->with('person')
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('row_id', $exclude))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('member_number', 'like', "%{$search}%")
                ->orWhereHas('person', fn ($person) => $person
                    ->where('national_identity_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%"))))
            ->orderBy('member_number')
            ->limit(20)
            ->get()
            ->map(fn (Member $member): array => $this->memberOption($member))
            ->values();

        return response()->json(['data' => $members]);
    }

    public function storeMember(QuickMemberRequest $request, MemberService $members): JsonResponse
    {
        $data = $request->validated();
        $village = OrganizationUnit::query()->villages()->active()->findOrFail($data['village_id']);

        try {
            $member = $members->create([
                ...$data,
                'birth_place' => null,
                'birth_date' => null,
                'phone' => null,
                'family_card_number' => null,
                'address' => $village->name,
                'registered_at' => now()->toDateString(),
                'status' => 'active',
                'has_guarantor' => false,
                'has_business' => false,
            ], (int) $request->user()->row_id);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'uq_people_nik')) {
                throw $exception;
            }

            throw ValidationException::withMessages(['nik' => 'NIK sudah terdaftar.']);
        }

        return response()->json(['data' => $this->memberOption($member)], 201);
    }

    private function formOptions(): array
    {
        return [
            'villages' => OrganizationUnit::query()->villages()->active()->orderBy('name')->get(['row_id', 'name'])->toArray(),
            'businessTypes' => $this->options(BusinessType::class),
            'activityTypes' => $this->options(ActivityType::class),
            'groupLevels' => $this->options(GroupLevel::class),
            'groupFunctions' => $this->options(GroupFunction::class),
        ];
    }

    private function options(string $model): array
    {
        return $model::query()->where('is_active', true)->orderBy('name')->get(['row_id', 'name'])->toArray();
    }

    private function formGroup(Group $group): array
    {
        $officers = $group->activeOfficers->keyBy('position');
        $members = $group->activeMemberships->map(fn ($membership): array => $this->memberOption($membership->member))->values()->all();

        return [
            ...$group->only(['row_id', 'id', 'code', 'name', 'address', 'phone', 'status']),
            'village_id' => $group->organization_unit_row_id,
            'business_type_id' => $group->business_type_row_id,
            'activity_type_id' => $group->activity_type_row_id,
            'group_level_id' => $group->group_level_row_id,
            'group_function_id' => $group->group_function_row_id,
            'established_at' => $group->established_at?->format('Y-m-d'),
            'members' => $members,
            'chair_id' => $officers->get('chair')?->member_row_id,
            'secretary_id' => $officers->get('secretary')?->member_row_id,
            'treasurer_id' => $officers->get('treasurer')?->member_row_id,
        ];
    }

    private function memberOption(Member $member): array
    {
        return [
            'value' => $member->row_id,
            'label' => $member->person?->full_name.' · '.$member->member_number,
            'member_number' => $member->member_number,
            'name' => $member->person?->full_name,
            'nik' => $member->person?->national_identity_number,
        ];
    }

    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [15, 30, 50, 100], true) ? $value : 15;
    }

    private function sort(string $value): string
    {
        return in_array($value, ['code', 'name', 'established_at', 'status', 'active_memberships_count'], true) ? $value : 'name';
    }

    private function direction(string $value): string
    {
        return in_array($value, ['asc', 'desc'], true) ? $value : 'asc';
    }
}
