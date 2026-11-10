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

    public function test_it_lists_only_users_of_the_tenant_that_owns_the_host(): void
    {
        $user = $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        $otherTenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['tenant-b.test']],
        ]);
        $this->createTenantUser($otherTenant, 'operator_b', 'Operator Tenant B');

        $this->get('http://tenant-a.test/login/user')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/UserLogin')
                ->where('tenant.code', 'tenant-a')
                ->has('users', 1)
                ->where('users.0.username', $user->username));
    }

    public function test_it_does_not_list_central_superadmin_users(): void
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

    public function test_it_signs_in_the_selected_tenant_user(): void
    {
        $user = $this->createTenantUser($this->testTenant, 'operator_a', 'Operator Tenant A');

        $this->post('http://tenant-a.test/login/user/'.$user->row_id)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_rejects_users_from_another_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['tenant-b.test']],
        ]);
        $otherUser = $this->createTenantUser($otherTenant, 'operator_b', 'Operator Tenant B');

        $this->post('http://tenant-a.test/login/user/'.$otherUser->row_id)
            ->assertNotFound();

        $this->assertGuest();
    }

    public function test_it_rejects_superadmin_accounts(): void
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
}
