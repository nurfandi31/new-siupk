<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantImpersonationToken;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantImpersonationTest extends TestCase
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

    public function test_superadmin_can_generate_impersonation_token_for_tenant(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant, $tenantUser] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju');

        $response = $this->actingAs($superadmin)
            ->postJson("/admin/tenants/{$tenant->row_id}/impersonate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('target_user.row_id', $tenantUser->row_id);

        $this->assertDatabaseHas('tenant_impersonation_tokens', [
            'tenant_id' => $tenant->row_id,
            'user_id' => $tenantUser->row_id,
            'impersonator_id' => $superadmin->row_id,
        ], 'platform');
    }

    public function test_non_superadmin_cannot_generate_impersonation_token(): void
    {
        [$tenant, $tenantUser] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju');

        // Tenant user is redirected to tenant dashboard
        $this->actingAs($tenantUser)
            ->postJson("/admin/tenants/{$tenant->row_id}/impersonate")
            ->assertRedirect(route('dashboard'));

        // Guest is rejected with 401 for JSON requests or redirected to login
        Auth::guard('web')->logout();
        $this->postJson("/admin/tenants/{$tenant->row_id}/impersonate")
            ->assertUnauthorized();
        $this->post("/admin/tenants/{$tenant->row_id}/impersonate")
            ->assertRedirect(route('login'));
    }

    public function test_impersonation_token_resolves_custom_domain_url(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju', [
            'domains' => ['bumdesma-sukamaju.id', 'app.sukamaju.desa.id'],
        ]);

        $response = $this->actingAs($superadmin)
            ->postJson("/admin/tenants/{$tenant->row_id}/impersonate");

        $response->assertOk();
        $redirectUrl = (string) $response->json('redirect_url');
        $this->assertStringContainsString('bumdesma-sukamaju.id/auth/impersonate/', $redirectUrl);
    }

    public function test_impersonation_token_can_use_specified_custom_domain(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju', [
            'domains' => ['bumdesma-sukamaju.id', 'app.sukamaju.desa.id'],
        ]);

        $response = $this->actingAs($superadmin)
            ->postJson("/admin/tenants/{$tenant->row_id}/impersonate", [
                'domain' => 'app.sukamaju.desa.id',
            ]);

        $response->assertOk();
        $redirectUrl = (string) $response->json('redirect_url');
        $this->assertStringContainsString('app.sukamaju.desa.id/auth/impersonate/', $redirectUrl);
    }

    public function test_superadmin_can_impersonate_specific_tenant_user(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant, $user1] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju');

        $user2 = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => 'Operator Kas',
            'username' => 'operator_kas',
            'email' => 'kas@sukamaju.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superadmin)
            ->postJson("/admin/tenants/{$tenant->row_id}/impersonate", [
                'user_id' => $user2->row_id,
            ]);

        $response->assertOk()
            ->assertJsonPath('target_user.row_id', $user2->row_id);

        $this->assertDatabaseHas('tenant_impersonation_tokens', [
            'tenant_id' => $tenant->row_id,
            'user_id' => $user2->row_id,
            'impersonator_id' => $superadmin->row_id,
        ], 'platform');
    }

    public function test_consuming_impersonation_token_logs_in_user_and_sets_session(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant, $tenantUser] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju');

        $token = Str::random(64);
        TenantImpersonationToken::query()->create([
            'token' => $token,
            'tenant_id' => $tenant->row_id,
            'user_id' => $tenantUser->row_id,
            'impersonator_id' => $superadmin->row_id,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->get("/auth/impersonate/{$token}");

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($tenantUser->row_id, Auth::guard('web')->id());
        $this->assertSame($superadmin->row_id, session('impersonated_by'));
        $this->assertSame($tenant->row_id, session('impersonated_tenant_id'));

        $this->assertNotNull(TenantImpersonationToken::query()->where('token', $token)->value('used_at'));
    }

    public function test_consumed_or_expired_token_is_rejected(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant, $tenantUser] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju');

        $token = Str::random(64);
        TenantImpersonationToken::query()->create([
            'token' => $token,
            'tenant_id' => $tenant->row_id,
            'user_id' => $tenantUser->row_id,
            'impersonator_id' => $superadmin->row_id,
            'expires_at' => now()->subMinute(), // Expired
        ]);

        $response = $this->get("/auth/impersonate/{$token}");

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_leaving_impersonation_restores_superadmin_session(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant, $tenantUser] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju');

        $this->actingAs($tenantUser)
            ->withSession([
                'impersonated_by' => $superadmin->row_id,
                'impersonator_name' => $superadmin->name,
                'impersonated_tenant_id' => $tenant->row_id,
            ])
            ->post('/auth/impersonate/leave')
            ->assertRedirect(route('admin.tenants.index'));

        $this->assertSame($superadmin->row_id, Auth::guard('web')->id());
        $this->assertNull(session('impersonated_by'));
    }

    public function test_impersonation_fails_for_suspended_tenant(): void
    {
        $superadmin = $this->createSuperadmin();
        [$tenant, $tenantUser] = $this->createTenantWithUser('sukamaju', 'BUMDesma Suka Maju');
        $tenant->forceFill(['status' => 'suspended'])->save();

        $response = $this->actingAs($superadmin)
            ->postJson("/admin/tenants/{$tenant->row_id}/impersonate");

        $response->assertStatus(500);
    }

    private function createSuperadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Administrator',
            'username' => 'superadmin_'.Str::lower(Str::random(6)),
            'email' => 'superadmin_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantWithUser(string $code, string $name, array $metadata = []): array
    {
        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'shard_'.Str::lower(Str::random(6)),
            'name' => 'Shard 1',
            'driver' => 'sqlite',
            'host' => 'localhost',
            'port' => 3306,
            'database_name' => ':memory:',
            'credential_reference' => 'local',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => $name,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => $metadata,
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => "Admin {$name}",
            'username' => "admin_{$code}",
            'email' => "admin@{$code}.test",
            'password' => 'password',
            'status' => 'active',
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$tenant, $user];
    }
}
