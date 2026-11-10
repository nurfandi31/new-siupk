<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CentralHostLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_tenant_user_cannot_login_through_the_central_host(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser([
            'tenant_id' => $tenant->row_id,
            'username' => 'demo.admin',
        ]);

        $response = $this->post('http://siupknext.test/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('identifier');
        $response->assertSessionHasErrors([
            'identifier' => 'Akun ini adalah akun tenant. Silakan login melalui domain tenant: demo.siupknext.test.',
        ]);
        $this->assertGuest();
    }

    public function test_tenant_user_can_still_login_through_their_tenant_host(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser([
            'tenant_id' => $tenant->row_id,
            'username' => 'demo.admin',
        ]);

        $response = $this->post('http://demo.siupknext.test/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_superadmin_can_login_through_the_central_host(): void
    {
        $admin = $this->createUser([
            'username' => 'superadmin_test',
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        $response = $this->post('http://siupknext.test/login', [
            'identifier' => $admin->username,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_tenant_session_cannot_open_tenant_dashboard_on_the_central_host(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser([
            'tenant_id' => $tenant->row_id,
            'username' => 'demo.admin',
        ]);

        $this->actingAs($user)
            ->get('http://siupknext.test/dashboard')
            ->assertForbidden();
    }

    private function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'demo',
            'name' => 'Demo Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['demo.siupknext.test']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'name' => 'Test User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'user_'.Str::lower(Str::random(12)),
            'password' => 'secret-password',
            'status' => 'active',
        ], $attributes));
    }
}
