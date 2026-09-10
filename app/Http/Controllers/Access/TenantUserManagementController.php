<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberUserLink;
use App\Http\Requests\Access\StoreTenantUserRequest;
use App\Http\Requests\Access\UpdateTenantUserRequest;
use App\Models\Platform\TenantMembership;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Services\PhoneNormalizer;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class TenantUserManagementController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->tenant();
        $search = trim((string) $request->query('search', ''));
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        $paginator = User::query()
            ->where('tenant_id', $tenant->row_id)
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $userIds = $paginator->getCollection()->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        $userRoles = UserRole::query()
            ->whereIn('platform_user_id', $userIds)
            ->with('role:row_id,code,name')
            ->get();

        $memberLinks = MemberUserLink::query()
            ->whereIn('user_row_id', $userIds)
            ->with('member.person:row_id,full_name')
            ->get()
            ->keyBy('user_row_id');

        $roleMap = [];
        foreach ($userRoles as $ur) {
            $roleMap[(int) $ur->platform_user_id] = [
                'code' => $ur->role?->code,
                'name' => $ur->role?->name,
            ];
        }

        $usersPayload = $paginator->through(fn (User $user): array => [
            'row_id' => $user->row_id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'is_village_user' => $user->is_village_user,
            'village_row_id' => $user->village_row_id,
            'role_code' => $roleMap[(int) $user->row_id]['code'] ?? null,
            'role_name' => $roleMap[(int) $user->row_id]['name'] ?? null,
            'member_row_id' => $memberLinks->get((int) $user->row_id)?->member_row_id,
            'member_name' => $memberLinks->get((int) $user->row_id)?->member?->person?->full_name,
            'appointed_at' => $user->appointed_at?->toDateString(),
            'term_end_at' => $user->term_end_at?->toDateString(),
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
            'is_self' => (int) $user->row_id === (int) $request->user()?->row_id,
        ]);

        return Inertia::render('Access/Users/Index', [
            'users' => $usersPayload,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => 'name',
            'direction' => 'asc',
        ]);
    }

    public function create(): Response
    {
        $this->permissions->ensureSystemRoles();
        $roles = $this->tenantRoleOptions();
        $villages = OrganizationUnit::query()->orderBy('name')->get(['row_id', 'name', 'code']);

        return Inertia::render('Access/Users/Form', [
            'user' => null,
            'roleOptions' => $roles,
            'villageOptions' => $villages->map(fn ($v) => ['value' => (int) $v->row_id, 'label' => "{$v->code} - {$v->name}"])->all(),
        ]);
    }

    public function store(StoreTenantUserRequest $request): RedirectResponse
    {
        $tenant = $this->tenantContext->tenant();
        $data = $request->validated();

        $user = DB::connection('platform')->transaction(function () use ($tenant, $data): User {
            $isVillage = ($data['role'] ?? null) === 'village_operator' || ! empty($data['is_village_user']);

            $user = User::query()->create([
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $tenant->row_id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => (new PhoneNormalizer)->normalize((string) $data['phone']),
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
                'appointed_at' => $data['appointed_at'] ?? null,
                'term_end_at' => $data['term_end_at'] ?? null,
                'is_village_user' => $isVillage,
                'village_row_id' => $isVillage && ! empty($data['village_row_id']) ? (int) $data['village_row_id'] : null,
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenant->row_id,
                'user_id' => $user->row_id,
                'status' => ($data['status'] ?? 'active') === 'active' ? 'active' : 'suspended',
                'joined_at' => now(),
            ]);

            return $user;
        });

        $this->syncUserRole($user, $data['role'] ?? null);
        $this->syncMemberLink($user, $data['role'] ?? null, $data['member_row_id'] ?? null);

        return to_route('access.users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): Response
    {
        $this->assertBelongs($user);
        $this->permissions->ensureSystemRoles();

        $userRole = UserRole::query()
            ->where('platform_user_id', (int) $user->row_id)
            ->with('role:row_id,code')
            ->first();
        $memberLink = MemberUserLink::query()
            ->where('user_row_id', (int) $user->row_id)
            ->with('member.person:row_id,full_name')
            ->first();

        $roles = $this->tenantRoleOptions();
        $villages = OrganizationUnit::query()->orderBy('name')->get(['row_id', 'name', 'code']);

        return Inertia::render('Access/Users/Form', [
            'user' => [
                ...$user->only(['row_id', 'name', 'username', 'email', 'status', 'is_village_user', 'village_row_id']),
                'appointed_at' => $user->appointed_at?->toDateString(),
                'term_end_at' => $user->term_end_at?->toDateString(),
                'role' => $userRole?->role?->code ?? '',
                'member_row_id' => $memberLink?->member_row_id,
                'member_name' => $memberLink?->member?->person?->full_name,
            ],
            'roleOptions' => $roles,
            'villageOptions' => $villages->map(fn ($v) => ['value' => (int) $v->row_id, 'label' => "{$v->code} - {$v->name}"])->all(),
        ]);
    }

    public function update(UpdateTenantUserRequest $request, User $user): RedirectResponse
    {
        $this->assertBelongs($user);
        $data = $request->validated();

        DB::connection('platform')->transaction(function () use ($user, $data): void {
            $isVillage = ($data['role'] ?? null) === 'village_operator' || ! empty($data['is_village_user']);

            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $this->normalizePhone($data['phone']),
                'username' => $data['username'],
                'status' => $data['status'],
                'appointed_at' => $data['appointed_at'] ?? null,
                'term_end_at' => $data['term_end_at'] ?? null,
                'is_village_user' => $isVillage,
                'village_row_id' => $isVillage && ! empty($data['village_row_id']) ? (int) $data['village_row_id'] : null,
            ])->save();

            if (! empty($data['password'])) {
                $user->forceFill(['password' => Hash::make($data['password'])])->save();
            }

            TenantMembership::query()
                ->where('user_id', $user->row_id)
                ->where('tenant_id', $user->tenant_id)
                ->update([
                    'status' => $data['status'] === 'active' ? 'active' : 'suspended',
                ]);
        });

        if (array_key_exists('role', $data)) {
            $this->syncUserRole($user, $data['role'] ?: null);
            $this->syncMemberLink($user, $data['role'] ?: null, $data['member_row_id'] ?? null);
        }

        return to_route('access.users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->assertBelongs($user);
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('success', "Password untuk {$user->name} berhasil direset.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->assertBelongs($user);

        if ((int) $user->row_id === (int) $request->user()?->row_id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // Check if user is the last admin
        $adminRole = Role::query()->where('code', 'admin')->first();
        if ($adminRole !== null) {
            $isAdmin = UserRole::query()
                ->where('platform_user_id', (int) $user->row_id)
                ->where('role_row_id', (int) $adminRole->row_id)
                ->exists();

            if ($isAdmin) {
                $otherAdminCount = UserRole::query()
                    ->where('role_row_id', (int) $adminRole->row_id)
                    ->where('platform_user_id', '!=', (int) $user->row_id)
                    ->count();

                if ($otherAdminCount === 0) {
                    return back()->with('error', 'Tidak dapat menghapus satu-satunya Administrator tenant.');
                }
            }
        }

        UserRole::query()->where('platform_user_id', (int) $user->row_id)->delete();

        DB::connection('platform')->transaction(function () use ($user): void {
            TenantMembership::query()
                ->where('user_id', $user->row_id)
                ->where('tenant_id', $user->tenant_id)
                ->delete();
            $user->delete();
        });

        return to_route('access.users.index')->with('success', 'Pengguna berhasil dihapus.');
    }

    private function syncUserRole(User $user, ?string $roleCode): void
    {
        UserRole::query()->where('platform_user_id', (int) $user->row_id)->delete();

        if ($roleCode === null || $roleCode === '') {
            return;
        }

        $role = Role::query()->where('code', $roleCode)->first();
        if ($role !== null) {
            UserRole::query()->firstOrCreate([
                'platform_user_id' => (int) $user->row_id,
                'role_row_id' => (int) $role->row_id,
            ]);
        }
    }

    private function syncMemberLink(User $user, ?string $roleCode, mixed $memberRowId): void
    {
        if ($roleCode !== 'anggota') {
            MemberUserLink::query()->where('user_row_id', (int) $user->row_id)->delete();

            return;
        }

        $memberRowId = (int) $memberRowId;
        abort_unless(Member::query()->whereKey($memberRowId)->exists(), 422, 'Anggota tidak ditemukan.');

        MemberUserLink::query()->updateOrCreate(
            ['user_row_id' => (int) $user->row_id],
            ['member_row_id' => $memberRowId],
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function tenantRoleOptions(): array
    {
        // Exclude supervisory level roles (regency/province)
        $excludedCodes = ['regency_supervisor', 'province_supervisor'];

        $roles = Role::query()
            ->whereNotIn('code', $excludedCodes)
            ->orderBy('name')
            ->get();

        $options = [
            ['value' => '', 'label' => 'Tanpa role (Akses Penuh Legacy)'],
        ];

        foreach ($roles as $r) {
            $options[] = [
                'value' => (string) $r->code,
                'label' => (string) $r->name.($r->code === 'admin' ? ' (Terkunci / Full Access)' : ''),
            ];
        }

        return $options;
    }

    private function assertBelongs(User $user): void
    {
        abort_unless((int) $user->tenant_id === $this->tenantContext->id(), 404);
    }

    private function normalizePhone(string $phone): string
    {
        return app(PhoneNormalizer::class)->normalize($phone);
    }
}
