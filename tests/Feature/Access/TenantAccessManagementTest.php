<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantAccessManagementTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->tenantAdmin = $this->createTenantAdminUser();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_tenant_admin_can_view_users_list(): void
    {
        $response = $this->actingAs($this->tenantAdmin)->get('/access/users');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Access/Users/Index')
            ->has('users.data')
        );
    }

    public function test_tenant_admin_can_create_new_tenant_user_with_role(): void
    {
        $role = Role::query()->create([
            'name' => 'Staf Keuangan',
            'code' => 'staf_keuangan',
            'permissions' => ['journals.view', 'journals.create'],
        ]);

        $response = $this->actingAs($this->tenantAdmin)->post('/access/users', [
            'name' => 'Staf Baru',
            'username' => 'staf_baru',
            'email' => 'staf_baru@example.test',
            'phone' => '081200000001',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'status' => 'active',
            'role' => 'staf_keuangan',
        ]);

        $response->assertRedirect('/access/users');

        $createdUser = User::query()->where('username', 'staf_baru')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame('Staf Baru', $createdUser->name);
        $this->assertSame((int) $this->testTenant->row_id, (int) $createdUser->tenant_id);

        $hasMembership = TenantMembership::query()
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('user_id', $createdUser->row_id)
            ->exists();
        $this->assertTrue($hasMembership);

        $userRole = UserRole::query()->where('platform_user_id', (int) $createdUser->row_id)->first();
        $this->assertNotNull($userRole);
        $this->assertSame((int) $role->row_id, (int) $userRole->role_row_id);

        $checker = app(PermissionChecker::class);
        $this->assertTrue($checker->allows($createdUser, 'journals.view'));
        $this->assertTrue($checker->allows($createdUser, 'journals.create'));
        $this->assertFalse($checker->allows($createdUser, 'loans.manage'));
    }

    public function test_tenant_admin_can_update_user_and_role(): void
    {
        $role1 = Role::query()->create([
            'name' => 'Role A',
            'code' => 'role_a',
            'permissions' => ['members.view'],
        ]);

        $role2 = Role::query()->create([
            'name' => 'Role B',
            'code' => 'role_b',
            'permissions' => ['loans.view'],
        ]);

        $user = $this->createRegularUser('testuser_edit');
        UserRole::query()->create([
            'platform_user_id' => $user->row_id,
            'role_row_id' => $role1->row_id,
        ]);

        $response = $this->actingAs($this->tenantAdmin)->put("/access/users/{$user->row_id}", [
            'name' => 'Updated User Name',
            'username' => 'testuser_edit_updated',
            'email' => 'updated_email@example.test',
            'phone' => '081200000002',
            'status' => 'suspended',
            'role' => 'role_b',
        ]);

        $response->assertRedirect('/access/users');

        $fresh = $user->fresh();
        $this->assertSame('Updated User Name', $fresh->name);
        $this->assertSame('testuser_edit_updated', $fresh->username);
        $this->assertSame('suspended', $fresh->status);

        $assignedRole = UserRole::query()->where('platform_user_id', (int) $user->row_id)->first();
        $this->assertNotNull($assignedRole);
        $this->assertSame((int) $role2->row_id, (int) $assignedRole->role_row_id);
    }

    public function test_tenant_admin_cannot_delete_own_account(): void
    {
        $response = $this->actingAs($this->tenantAdmin)->delete("/access/users/{$this->tenantAdmin->row_id}");

        $response->assertSessionHas('error');
        $this->assertNotNull(User::query()->find($this->tenantAdmin->row_id));
    }

    public function test_tenant_admin_can_view_roles_list(): void
    {
        $response = $this->actingAs($this->tenantAdmin)->get('/access/roles');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Access/Roles/Index')
            ->has('roles')
        );
    }

    public function test_admin_role_is_locked_from_modification_and_deletion(): void
    {
        app(PermissionChecker::class)->ensureSystemRoles();

        $adminRole = Role::query()->where('code', 'admin')->first();
        $this->assertNotNull($adminRole);

        // Attempt to update locked admin role
        $updateResponse = $this->actingAs($this->tenantAdmin)->put("/access/roles/{$adminRole->row_id}", [
            'name' => 'Hacked Admin',
            'code' => 'admin',
            'permissions' => [],
        ]);
        $updateResponse->assertSessionHas('error');
        $this->assertSame('admin', $adminRole->fresh()->code);

        // Attempt to delete locked admin role
        $deleteResponse = $this->actingAs($this->tenantAdmin)->delete("/access/roles/{$adminRole->row_id}");
        $deleteResponse->assertSessionHas('error');
        $this->assertNotNull($adminRole->fresh());
    }

    public function test_tenant_admin_can_create_and_manage_custom_roles(): void
    {
        // 1. Create custom role
        $createResponse = $this->actingAs($this->tenantAdmin)->post('/access/roles', [
            'name' => 'Verifikator Pinjaman',
            'code' => 'verifikator_pinjaman',
            'description' => 'Khusus verifikasi proposal pinjaman',
            'permissions' => ['loans.view', 'loans.verify'],
        ]);
        $createResponse->assertRedirect('/access/roles');

        $role = Role::query()->where('code', 'verifikator_pinjaman')->first();
        $this->assertNotNull($role);
        $this->assertSame(['loans.view', 'loans.verify'], $role->permissions);

        // 2. Update custom role
        $updateResponse = $this->actingAs($this->tenantAdmin)->put("/access/roles/{$role->row_id}", [
            'name' => 'Verifikator & Surveyor',
            'code' => 'verifikator_pinjaman',
            'description' => 'Update deskripsi',
            'permissions' => ['loans.view', 'loans.verify', 'loans.approve'],
        ]);
        $updateResponse->assertRedirect('/access/roles');

        $freshRole = $role->fresh();
        $this->assertSame('Verifikator & Surveyor', $freshRole->name);
        $this->assertSame(['loans.view', 'loans.verify', 'loans.approve'], $freshRole->permissions);

        // 3. Delete custom role (when unused)
        $deleteResponse = $this->actingAs($this->tenantAdmin)->delete("/access/roles/{$role->row_id}");
        $deleteResponse->assertRedirect('/access/roles');
        $this->assertNull(Role::query()->where('code', 'verifikator_pinjaman')->first());
    }

    public function test_cannot_delete_role_assigned_to_active_users(): void
    {
        $role = Role::query()->create([
            'name' => 'Role Terpakai',
            'code' => 'role_terpakai',
            'permissions' => ['members.view'],
        ]);

        $user = $this->createRegularUser('assigned_user');
        UserRole::query()->create([
            'platform_user_id' => $user->row_id,
            'role_row_id' => $role->row_id,
        ]);

        $response = $this->actingAs($this->tenantAdmin)->delete("/access/roles/{$role->row_id}");
        $response->assertSessionHas('error');
        $this->assertNotNull($role->fresh());
    }

    private function createTenantAdminUser(): User
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Admin Tenant',
            'username' => 'admintenant_'.Str::lower(Str::random(5)),
            'email' => 'admin_'.Str::lower(Str::random(5)).'@example.test',
            'password' => 'secret',
            'status' => 'active',
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionChecker::class)->ensureSystemRoles();
        $adminRole = Role::query()->where('code', 'admin')->first();
        if ($adminRole !== null) {
            UserRole::query()->create([
                'platform_user_id' => $user->row_id,
                'role_row_id' => $adminRole->row_id,
            ]);
        }

        return $user;
    }

    private function createRegularUser(string $username): User
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Regular User',
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'secret',
            'status' => 'active',
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }
}
