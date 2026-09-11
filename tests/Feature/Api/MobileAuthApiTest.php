<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MobileAuthApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Kolektor Lapangan',
            'username' => 'kolektor1',
            'email' => 'kolektor@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'user_id' => $this->user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_user_can_login_via_mobile_api_with_username(): void
    {
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'identifier' => 'kolektor1',
            'password' => 'password123',
            'device_name' => 'Redmi Note 12',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('data.user.username', 'kolektor1')
            ->assertJsonPath('data.user.name', 'Kolektor Lapangan')
            ->assertJsonPath('data.tenant.code', 'tenant-a')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'username',
                        'email',
                        'phone',
                        'is_superadmin',
                        'is_regency_user',
                        'is_province_user',
                        'is_village_user',
                        'village_row_id',
                    ],
                    'tenant' => [
                        'id',
                        'code',
                        'name',
                        'status',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_can_login_via_mobile_api_with_email(): void
    {
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'identifier' => 'kolektor@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'kolektor1');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'identifier' => 'kolektor1',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Kredensial yang diberikan tidak valid.');
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/mobile/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_authenticated_user_can_get_profile_and_permissions(): void
    {
        $token = $this->user->createToken('Test Mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mobile/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'kolektor1')
            ->assertJsonPath('data.tenant.code', 'tenant-a')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'username',
                        'email',
                        'permissions',
                    ],
                    'tenant',
                ],
            ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/mobile/auth/me');

        $response->assertStatus(401);
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $token = $this->user->createToken('Test Mobile')->plainTextToken;

        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/mobile/auth/logout');

        $logoutResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logout berhasil.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->user->row_id,
        ], 'platform');

        $this->app['auth']->forgetGuards();

        $profileResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mobile/auth/me');

        $profileResponse->assertStatus(401);
    }
}
