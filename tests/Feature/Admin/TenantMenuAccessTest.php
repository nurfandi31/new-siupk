<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionChecker;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantMenuAccessTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([PreventRequestForgery::class, ResolveTenant::class]);
        $this->rebuildTenantTestDatabases();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_superadmin_can_open_menu_access_page(): void
    {
        app(PermissionChecker::class)->ensureSystemRoles();

        $this->actingAs($this->superadmin())
            ->get("/admin/tenants/{$this->testTenant->row_id}/menus")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Menus/Index')
                ->has('roles')
                ->has('totalMenus')
            );
    }

    public function test_superadmin_can_restrict_role_menus(): void
    {
        $role = $this->createRole('bendahara', ['loans.view', 'journals.view']);

        $this->actingAs($this->superadmin())
            ->put("/admin/tenants/{$this->testTenant->row_id}/menus/{$role->row_id}", [
                'restricted' => true,
                'menus' => ['journals.index'],
            ])
            ->assertRedirect(route('admin.tenants.menus.index', $this->testTenant));

        $stored = $this->workbench()->run(
            $this->testTenant,
            fn () => Role::query()->findOrFail($role->row_id)->menu_access,
        );

        $this->assertSame(['journals.index'], $stored);
    }

    public function test_admin_role_cannot_be_restricted(): void
    {
        app(PermissionChecker::class)->ensureSystemRoles();
        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();

        $this->actingAs($this->superadmin())
            ->put("/admin/tenants/{$this->testTenant->row_id}/menus/{$adminRole->row_id}", [
                'restricted' => true,
                'menus' => ['journals.index'],
            ])
            ->assertSessionHas('error');

        $stored = $this->workbench()->run(
            $this->testTenant,
            fn () => Role::query()->findOrFail($adminRole->row_id)->menu_access,
        );

        $this->assertNull($stored);
    }

    public function test_restricted_role_is_blocked_from_unlisted_menu_but_can_open_listed_menu(): void
    {
        $role = $this->createRole('staf_terbatas', ['users.view', 'roles.view'], ['access.users']);
        $user = $this->createTenantUserWithRole($role);

        $this->actingAs($user)
            ->get('/access/roles')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/access/users')
            ->assertOk();
    }

    public function test_role_without_menu_configuration_keeps_full_menu_access(): void
    {
        $role = $this->createRole('staf_biasa', ['users.view', 'roles.view']);
        $user = $this->createTenantUserWithRole($role);

        $this->actingAs($user)
            ->get('/access/roles')
            ->assertOk();
    }

    private function createRole(string $code, array $permissions, ?array $menuAccess = null): Role
    {
        return Role::query()->create([
            'name' => ucfirst(str_replace('_', ' ', $code)),
            'code' => $code,
            'is_system' => false,
            'permissions' => $permissions,
            'menu_access' => $menuAccess,
        ]);
    }

    private function createTenantUserWithRole(Role $role): User
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Tenant Staff',
            'username' => 'staff_'.Str::lower(Str::random(6)),
            'email' => 'staff_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        UserRole::query()->create([
            'platform_user_id' => (int) $user->row_id,
            'role_row_id' => (int) $role->row_id,
        ]);

        return $user;
    }

    private function workbench(): TenantWorkbench
    {
        return app(TenantWorkbench::class);
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => null,
            'name' => 'Super Admin',
            'username' => 'superadmin_'.Str::lower(Str::random(5)),
            'email' => 'superadmin_'.Str::lower(Str::random(5)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }
}
