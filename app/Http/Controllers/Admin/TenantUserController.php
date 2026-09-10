<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreTenantUserRequest;
use App\Http\Requests\Admin\UpdateTenantUserRequest;
use App\Models\Platform\Tenant;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\TenantUserService;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class TenantUserController
{
    public function index(Request $request, Tenant $tenant, TenantUserService $users): Response
    {
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

        $roleMap = $users->rolesForMany(
            $tenant,
            $paginator->getCollection()->pluck('row_id')->map(fn ($id) => (int) $id)->all(),
        );

        $usersPayload = $paginator->through(fn (User $user): array => [
            'row_id' => $user->row_id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'is_village_user' => $user->is_village_user,
            'village_row_id' => $user->village_row_id,
            'role' => $roleMap[(int) $user->row_id][0] ?? null,
            'roles' => $roleMap[(int) $user->row_id] ?? [],
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
        ]);

        return Inertia::render('Admin/Tenants/Users/Index', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'users' => $usersPayload,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => 'name',
            'direction' => 'asc',
        ]);
    }

    public function create(Tenant $tenant, TenantWorkbench $workbench): Response
    {
        $villages = $workbench->run($tenant, fn () => OrganizationUnit::query()->orderBy('name')->get(['row_id', 'name', 'code']));

        return Inertia::render('Admin/Tenants/Users/Form', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'user' => null,
            'roleOptions' => $this->roleOptions(),
            'villageOptions' => $villages->map(fn ($v) => ['value' => (int) $v->row_id, 'label' => "{$v->code} - {$v->name}"])->all(),
        ]);
    }

    public function store(StoreTenantUserRequest $request, Tenant $tenant, TenantUserService $users, AuditLogger $audit): RedirectResponse
    {
        $user = $users->create($tenant, $request->validated());

        $audit->record(
            'tenant_user.create',
            $tenant,
            User::class,
            $user->row_id,
            sprintf('User [%s] dibuat pada tenant [%s].', $user->username, $tenant->code),
        );

        return to_route('admin.tenants.users.index', $tenant)->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(Tenant $tenant, User $user, TenantUserService $users, TenantWorkbench $workbench): Response
    {
        $this->assertBelongs($tenant, $user);
        $roles = $users->rolesFor($tenant, $user);
        $villages = $workbench->run($tenant, fn () => OrganizationUnit::query()->orderBy('name')->get(['row_id', 'name', 'code']));

        return Inertia::render('Admin/Tenants/Users/Form', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'user' => [
                ...$user->only(['row_id', 'name', 'username', 'email', 'status', 'is_village_user', 'village_row_id']),
                'role' => $roles[0] ?? null,
            ],
            'roleOptions' => $this->roleOptions(),
            'villageOptions' => $villages->map(fn ($v) => ['value' => (int) $v->row_id, 'label' => "{$v->code} - {$v->name}"])->all(),
        ]);
    }

    public function update(UpdateTenantUserRequest $request, Tenant $tenant, User $user, TenantUserService $users, AuditLogger $audit): RedirectResponse
    {
        $this->assertBelongs($tenant, $user);
        $before = $user->only(['name', 'username', 'email', 'status']);
        $users->update($tenant, $user, $request->validated());

        $audit->record(
            'tenant_user.update',
            $tenant,
            User::class,
            $user->row_id,
            sprintf('User [%s] diperbarui pada tenant [%s].', $user->username, $tenant->code),
            ['changes' => AuditLogger::diff($before, $user->only(['name', 'username', 'email', 'status']))],
        );

        return to_route('admin.tenants.users.index', $tenant)->with('success', 'Pengguna diperbarui.');
    }

    public function resetPassword(Request $request, Tenant $tenant, User $user, TenantUserService $users, AuditLogger $audit): RedirectResponse
    {
        $this->assertBelongs($tenant, $user);
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);
        $users->resetPassword($user, $data['password']);

        $audit->record(
            'tenant_user.reset_password',
            $tenant,
            User::class,
            $user->row_id,
            sprintf('Password user [%s] pada tenant [%s] direset.', $user->username, $tenant->code),
        );

        return back()->with('success', 'Password direset.');
    }

    /** @return list<array{value: string, label: string}> */
    private function roleOptions(): array
    {
        $options = [
            ['value' => '', 'label' => 'Tanpa role (akses penuh legacy)'],
        ];
        foreach (config('permissions.roles', []) as $code => $def) {
            $options[] = [
                'value' => (string) $code,
                'label' => (string) ($def['name'] ?? $code),
            ];
        }

        return $options;
    }

    private function assertBelongs(Tenant $tenant, User $user): void
    {
        abort_unless((int) $user->tenant_id === (int) $tenant->row_id, 404);
    }
}
