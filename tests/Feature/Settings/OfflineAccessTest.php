<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\OfflineAccessService;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class OfflineAccessTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $admin;

    private User $collector;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();

        $this->admin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Admin Tenant',
            'username' => 'admin_tenant',
            'email' => 'admin@tenant.test',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);

        $this->collector = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Collector Offline',
            'username' => 'collector_offline',
            'email' => 'collector@tenant.test',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);

        $this->outsider = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Not Selected',
            'username' => 'not_selected',
            'email' => 'other@tenant.test',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_admin_can_enable_offline_access_for_one_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/settings/offline-access', [
                'is_enabled' => true,
                'user_id' => $this->collector->row_id,
            ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'offline'], false));

        $service = app(OfflineAccessService::class);
        $tenantId = $this->testTenant->row_id;

        $this->assertTrue($service->isEnabled($tenantId));
        $this->assertSame((int) $this->collector->row_id, $service->userId($tenantId));
    }

    public function test_selected_user_is_allowed_but_others_are_not(): void
    {
        $service = app(OfflineAccessService::class);
        $tenantId = $this->testTenant->row_id;

        $service->save($tenantId, true, (int) $this->collector->row_id);

        $this->assertTrue($service->isUserAllowed($tenantId, (int) $this->collector->row_id));
        $this->assertFalse($service->isUserAllowed($tenantId, (int) $this->outsider->row_id));
        $this->assertFalse($service->isUserAllowed($tenantId, null));
    }

    public function test_disabling_offline_access_blocks_everyone(): void
    {
        $service = app(OfflineAccessService::class);
        $tenantId = $this->testTenant->row_id;

        $service->save($tenantId, true, (int) $this->collector->row_id);
        $service->save($tenantId, false, null);

        $this->assertFalse($service->isEnabled($tenantId));
        $this->assertNull($service->userId($tenantId));
        $this->assertFalse($service->isUserAllowed($tenantId, (int) $this->collector->row_id));
    }

    public function test_cannot_enable_without_selecting_user(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pengguna offline wajib dipilih.');

        $service = app(OfflineAccessService::class);
        $service->save($this->testTenant->row_id, true, null);
    }

    public function test_offline_user_can_mutate_while_others_are_blocked(): void
    {
        $service = app(OfflineAccessService::class);
        $tenantId = $this->testTenant->row_id;
        $service->save($tenantId, true, (int) $this->collector->row_id);

        // Offline collector should NOT be blocked (middleware allows)
        $collectorResponse = $this->actingAs($this->collector)
            ->withHeader('X-Client-Offline', 'true')
            ->putJson('/settings/offline-access', [
                'is_enabled' => true,
                'user_id' => $this->collector->row_id,
            ]);
        $this->assertNotSame(403, $collectorResponse->getStatusCode());

        // Non-selected user should still be blocked
        $outsiderResponse = $this->actingAs($this->outsider)
            ->withHeader('X-Client-Offline', 'true')
            ->putJson('/settings/offline-access', [
                'is_enabled' => true,
                'user_id' => $this->outsider->row_id,
            ]);
        $this->assertContains($outsiderResponse->getStatusCode(), [401, 403]);
    }

    public function test_offline_middleware_whitelists_mobile_auth(): void
    {
        $response = $this->withHeader('X-Client-Offline', 'true')
            ->postJson('/api/v1/mobile/auth/login', []);

        $this->assertNotSame(403, $response->getStatusCode());
    }
}
