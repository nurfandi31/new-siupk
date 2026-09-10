<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\EntityLoanHistoryService;
use App\Domain\Membership\Services\MasterDataCsvService;
use App\Domain\Membership\Services\MemberService;
use App\Http\Requests\MasterData\MemberRequest;
use App\Models\Tenant\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MemberController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = $this->sort((string) $request->query('sort', 'name'));
        $direction = $this->direction((string) $request->query('direction', 'asc'));
        $members = Member::query()
            ->with(['person', 'village'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('member_number', 'like', "%{$search}%")
                ->orWhereHas('person', fn ($person) => $person
                    ->where('national_identity_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
            ->when(in_array($sort, ['name', 'nik', 'phone'], true), function ($query) use ($sort, $direction): void {
                $column = ['name' => 'full_name', 'nik' => 'national_identity_number', 'phone' => 'phone'][$sort];
                $query->orderBy(Person::query()->select($column)->whereColumn('people.row_id', 'members.person_row_id'), $direction);
            }, fn ($query) => $query->orderBy($sort, $direction))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Member $member): array => [
                'row_id' => $member->row_id,
                'id' => $member->id,
                'member_number' => $member->member_number,
                'nik' => $member->person?->national_identity_number,
                'name' => $member->person?->full_name,
                'phone' => $member->person?->phone,
                'village' => $member->village?->only(['row_id', 'name']),
                'registered_at' => $member->registered_at?->format('Y-m-d'),
                'status' => $member->status,
            ]);

        return Inertia::render('MasterData/Members/Index', compact('members', 'search', 'perPage', 'sort', 'direction'));
    }

    public function create(): Response
    {
        return Inertia::render('MasterData/Members/Form', [
            'member' => null,
            'villages' => $this->villages(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate(['nik' => ['required', 'digits:16']]);
        $member = Member::query()
            ->whereHas('person', fn ($query) => $query->where('national_identity_number', $validated['nik']))
            ->with(['person', 'village', 'address', 'business', 'guarantor.person'])
            ->first();

        return response()->json(['data' => $member ? $this->formMember($member) : null]);
    }

    public function store(MemberRequest $request, MemberService $members): RedirectResponse
    {
        $members->create($request->validated(), (int) $request->user()->row_id);

        return to_route('master-data.members.index')->with('success', 'Anggota berhasil ditambahkan.');
    }

    public function show(Member $member, EntityLoanHistoryService $loanHistory): Response
    {
        $member->load([
            'person',
            'village',
            'address',
            'business',
            'guarantor.person',
            'groupMemberships' => fn ($q) => $q->where('status', 'active')->whereNull('left_at')->with('group:row_id,id,code,name,status'),
        ]);

        $loans = $loanHistory->forMember((int) $member->row_id);
        $activeLoans = collect($loans)->whereIn('status', ['active', 'disbursed'])->count();
        $outstanding = collect($loans)->sum(fn (array $l): float => (float) ($l['principal_remaining'] ?? 0));

        return Inertia::render('MasterData/Members/Show', [
            'member' => [
                ...$this->formMember($member),
                'member_number' => $member->member_number,
                'public_id' => $member->public_id,
                'groups' => $member->groupMemberships
                    ->map(fn ($gm) => $gm->group ? [
                        'row_id' => (int) $gm->group->row_id,
                        'id' => (int) $gm->group->id,
                        'code' => $gm->group->code,
                        'name' => $gm->group->name,
                        'status' => $gm->group->status,
                        'href' => '/master-data/groups/'.$gm->group->row_id,
                    ] : null)
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'loans' => $loans,
            'summary' => [
                'loan_count' => count($loans),
                'active_loan_count' => $activeLoans,
                'principal_remaining' => round((float) $outstanding, 2),
            ],
        ]);
    }

    public function uploadIdentityPhoto(Request $request, Member $member): RedirectResponse
    {
        app(PermissionChecker::class)->denyUnless($request->user(), 'members.manage');

        $validated = $request->validate([
            'identity_photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],
        ]);

        $person = $member->person()->firstOrFail();
        $file = $request->file('identity_photo');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        if ($person->identity_photo_path) {
            Storage::disk('public')->delete($person->identity_photo_path);
        }

        $person->identity_photo_path = $file->storeAs(
            'identity-photos/'.$person->getKey(),
            'ktp.'.$extension,
            'public',
        );
        $person->touch();
        $person->save();

        return to_route('master-data.members.show', $member->getKey())
            ->with('success', 'Foto KTP anggota berhasil diunggah.');
    }

    public function destroyIdentityPhoto(Request $request, Member $member): RedirectResponse
    {
        app(PermissionChecker::class)->denyUnless($request->user(), 'members.manage');

        $person = $member->person()->firstOrFail();

        if ($person->identity_photo_path) {
            Storage::disk('public')->delete($person->identity_photo_path);
            $person->identity_photo_path = null;
            $person->touch();
            $person->save();
        }

        return to_route('master-data.members.show', $member->getKey())
            ->with('success', 'Foto KTP anggota dihapus.');
    }

    public function edit(Member $member): Response
    {
        return Inertia::render('MasterData/Members/Form', [
            'member' => $this->formMember($member->load(['person', 'village', 'address', 'business', 'guarantor.person'])),
            'villages' => $this->villages(),
        ]);
    }

    public function update(MemberRequest $request, Member $member, MemberService $members): RedirectResponse
    {
        $members->update($member, $request->validated());

        return to_route('master-data.members.index')->with('success', 'Anggota berhasil diperbarui.');
    }

    public function destroy(Request $request, Member $member, PermissionChecker $permissions): RedirectResponse
    {
        $permissions->denyUnless($request->user(), 'members.manage');

        $tenantId = $member->tenant_id;
        $memberId = (int) $member->row_id;

        $hasGroup = DB::connection('tenant')->table('group_members')
            ->where('tenant_id', $tenantId)
            ->where('member_row_id', $memberId)
            ->exists();

        if ($hasGroup) {
            return back()->with('error', 'Anggota tidak dapat dihapus karena masih terdaftar di dalam kelompok.');
        }

        $hasBeneficiary = DB::connection('tenant')->table('loan_beneficiaries')
            ->where('tenant_id', $tenantId)
            ->where('member_row_id', $memberId)
            ->exists();

        if ($hasBeneficiary) {
            return back()->with('error', 'Anggota tidak dapat dihapus karena memiliki riwayat pemanfaat pinjaman.');
        }

        $hasBorrower = DB::connection('tenant')->table('loan_borrowers')
            ->where('tenant_id', $tenantId)
            ->where('member_row_id', $memberId)
            ->exists();

        if ($hasBorrower) {
            return back()->with('error', 'Anggota tidak dapat dihapus karena memiliki riwayat pinjaman perorangan.');
        }

        $hasCommittee = DB::connection('tenant')->table('loan_committee')
            ->where('tenant_id', $tenantId)
            ->where('member_row_id', $memberId)
            ->exists();

        if ($hasCommittee) {
            return back()->with('error', 'Anggota tidak dapat dihapus karena tercatat sebagai pengurus proposal pinjaman.');
        }

        $hasOfficer = DB::connection('tenant')->table('group_officers')
            ->where('tenant_id', $tenantId)
            ->where('member_row_id', $memberId)
            ->exists();

        if ($hasOfficer) {
            return back()->with('error', 'Anggota tidak dapat dihapus karena tercatat sebagai pengurus kelompok.');
        }

        DB::connection('tenant')->transaction(function () use ($member, $tenantId): void {
            $person = $member->person;
            $member->delete();

            if ($person !== null) {
                $otherMemberCount = DB::connection('tenant')->table('members')
                    ->where('tenant_id', $tenantId)
                    ->where('person_row_id', $person->row_id)
                    ->count();

                if ($otherMemberCount === 0) {
                    $person->delete();
                }
            }
        });

        return to_route('master-data.members.index')->with('success', 'Anggota berhasil dihapus.');
    }

    public function export(MasterDataCsvService $csv): StreamedResponse
    {
        return $csv->exportMembers();
    }

    public function import(Request $request, MasterDataCsvService $csv): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [], ['file' => 'file CSV']);

        $result = $csv->importMembers($request->file('file'), (int) $request->user()->row_id);

        return $this->importFlash('master-data.members.index', 'Anggota', $result);
    }

    /**
     * @param  array{imported: int, skipped: int, errors: array<int, string>}  $result
     */
    private function importFlash(string $route, string $label, array $result): RedirectResponse
    {
        $message = sprintf(
            'Impor %s selesai. Berhasil: %d, dilewati: %d.',
            strtolower($label),
            $result['imported'],
            $result['skipped'],
        );

        if ($result['errors'] !== []) {
            $preview = implode(' ', array_slice($result['errors'], 0, 3));
            $extra = count($result['errors']) > 3 ? ' …' : '';

            return to_route($route)->with('warning', $message.' Error: '.$preview.$extra);
        }

        return to_route($route)->with('success', $message);
    }

    private function villages(): array
    {
        return OrganizationUnit::query()->villages()->active()->orderBy('name')->get(['row_id', 'name'])->toArray();
    }

    private function formMember(Member $member): array
    {
        return [
            'row_id' => $member->row_id,
            'id' => $member->id,
            'nik' => $member->person?->national_identity_number,
            'name' => $member->person?->full_name,
            'gender' => $member->person?->gender,
            'birth_place' => $member->person?->birth_place,
            'birth_date' => $member->person?->birth_date?->format('Y-m-d'),
            'phone' => $member->person?->phone,
            'family_card_number' => $member->person?->family_card_number,
            'address' => $member->address?->address,
            'village_id' => $member->organization_unit_row_id,
            'village' => $member->village?->only(['row_id', 'name', 'code']),
            'registered_at' => $member->registered_at?->format('Y-m-d'),
            'status' => $member->status,
            'identity_photo_path' => $member->person?->identity_photo_path,
            'identity_photo_updated_at' => $member->person?->updated_at?->timestamp,
            'guarantor' => $member->guarantor ? [
                'nik' => $member->guarantor->person?->national_identity_number,
                'name' => $member->guarantor->person?->full_name,
                'relationship' => $member->guarantor->relationship_type,
            ] : null,
            'business' => $member->business?->only(['name', 'description']),
        ];
    }

    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [15, 30, 50, 100], true) ? $value : 15;
    }

    private function sort(string $value): string
    {
        return ['member_number' => 'member_number', 'registered_at' => 'registered_at', 'status' => 'status', 'name' => 'name', 'nik' => 'nik', 'phone' => 'phone'][$value] ?? 'name';
    }

    private function direction(string $value): string
    {
        return in_array($value, ['asc', 'desc'], true) ? $value : 'asc';
    }
}
