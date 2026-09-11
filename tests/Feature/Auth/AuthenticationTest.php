<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        DB::connection('platform')->disconnect();
        DB::connection('tenant')->disconnect();

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_login_page_is_public(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_valid_user_can_login(): void
    {
        $user = $this->createUser(['password' => 'secret-password']);

        $response = $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_superadmin_login_goes_to_admin_not_intended_admin_trap(): void
    {
        $admin = $this->createUser([
            'username' => 'superadmin_test',
            'password' => 'secret-password',
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        // Guest hit /admin first → session url.intended = /admin
        $this->get('/admin')->assertRedirect('/login');

        $this->post('/login', [
            'identifier' => $admin->username,
            'password' => 'secret-password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    public function test_tenant_user_cannot_use_stale_admin_intended(): void
    {
        $user = $this->createUser(['password' => 'secret-password']);

        $this->get('/admin')->assertRedirect('/login');

        $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
        ])->assertRedirect('/dashboard');
    }

    public function test_logged_in_superadmin_visiting_login_goes_to_admin_not_dashboard(): void
    {
        $admin = $this->createUser([
            'username' => 'superadmin_guest_mw',
            'password' => 'secret-password',
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect('/admin');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->createUser(['password' => 'secret-password']);

        $this->from('/login')
            ->post('/login', [
                'identifier' => $user->username,
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->createUser([
            'password' => 'secret-password',
            'status' => 'suspended',
        ]);

        $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_login_redirect_normalizes_http_intended_to_https_when_request_is_secure(): void
    {
        $user = $this->createUser(['password' => 'secret-password']);

        // Stale HTTP intended URL in session
        $this->withSession(['url.intended' => 'http://next.siupk.net/dashboard'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->post('/login', [
                'identifier' => $user->username,
                'password' => 'secret-password',
            ])
            ->assertRedirect('https://next.siupk.net/dashboard');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'name' => 'Test User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'user_'.Str::lower(Str::random(12)),
            'password' => 'password',
            'status' => 'active',
        ], $attributes));
    }
}
