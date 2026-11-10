<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class UserLoginTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['tenant-a.test']],
        ])->save();
    }

    public function test_central_page_lists_only_platform_users(): void
    {
        $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');
        $superadmin = $this->createCentralUser('superadmin', 'Super Admin', ['is_superadmin' => true]);
        $this->createCentralUser('province_supervisor', 'Supervisor Provinsi', ['is_province_user' => true]);

        $this->get('http://localhost/user')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/UserLogin')
                ->where('scope', 'central')
                ->has('users', 2)
                ->where('users.0.row_id', $superadmin->row_id));
    }

    public function test_central_page_never_exposes_tenant_users(): void
    {
        $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        $this->get('http://localhost/user')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'central')
                ->has('users', 0));
    }

    public function test_central_page_redirects_a_tenant_host_to_the_automatic_tenant_url(): void
    {
        $this->get('http://tenant-a.test/user')
            ->assertRedirect('http://tenant-a.test/login/user');
    }

    public function test_tenant_page_lists_only_users_of_the_location_resolved_from_the_host(): void
    {
        $user = $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        $otherTenant = $this->createTenant('tenant-b', 'tenant-b.test');
        $this->createTenantUser($otherTenant, 'operator_b', 'Operator Tenant B');

        $this->createCentralUser('superadmin', 'Super Admin', ['is_superadmin' => true]);

        $this->get('http://tenant-a.test/login/user')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/UserLogin')
                ->where('scope', 'tenant')
                ->where('context.code', 'tenant-a')
                ->has('users', 1)
                ->where('users.0.username', $user->username));
    }

    public function test_tenant_page_does_not_list_central_superadmin_users(): void
    {
        $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        $this->get('http://tenant-a.test/login/user')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users', 1)
                ->where('users.0.username', 'operator_a'));
    }

    public function test_explicit_tenant_url_still_filters_by_the_tenant_in_the_url(): void
    {
        $user = $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        $this->get('http://tenant-a.test/t/tenant-a/user')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('context.code', 'tenant-a')
                ->has('users', 1)
                ->where('users.0.username', $user->username));
    }

    public function test_it_signs_in_the_selected_tenant_user(): void
    {
        $user = $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        $this->post('http://tenant-a.test/login/user/'.$user->row_id)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_signs_in_the_selected_central_user(): void
    {
        $admin = $this->createCentralUser('superadmin', 'Super Admin', ['is_superadmin' => true]);

        $this->post('http://localhost/user/'.$admin->row_id)
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_central_auto_login_rejects_tenant_users(): void
    {
        $user = $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        $this->post('http://localhost/user/'.$user->row_id)
            ->assertNotFound();

        $this->assertGuest();
    }

    public function test_it_rejects_users_from_another_tenant(): void
    {
        $otherTenant = $this->createTenant('tenant-b', 'tenant-b.test');
        $otherUser = $this->createTenantUser($otherTenant, 'operator_b', 'Operator Tenant B');

        $this->post('http://tenant-a.test/login/user/'.$otherUser->row_id)
            ->assertNotFound();

        $this->assertGuest();
    }

    public function test_tenant_auto_login_rejects_superadmin_accounts(): void
    {
        $superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        $this->post('http://tenant-a.test/login/user/'.$superadmin->row_id)
            ->assertNotFound();

        $this->assertGuest();
    }

    private function createTenant(string $code, string $host): Tenant
    {
        return Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => 'Tenant '.$code,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => [$host]],
        ]);
    }

    private function createTenantUser(Tenant $tenant, string $username, string $name): User
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => $name,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCentralUser(string $username, string $name, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => null,
            'name' => $name,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'status' => 'active',
        ], $attributes));
    }
}
