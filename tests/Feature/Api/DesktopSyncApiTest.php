<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class DesktopSyncApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_can_get_desktop_sync_status_by_tenant_code(): void
    {
        $response = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/status');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('tenant.code', 'tenant-a')
            ->assertJsonPath('tenant.name', 'Tenant A')
            ->assertJsonStructure([
                'status',
                'server_time',
                'app_name',
                'app_version',
                'tenant' => [
                    'id',
                    'code',
                    'name',
                    'status',
                    'district_code',
                    'regency_code',
                    'regency_name',
                    'province_code',
                    'shard',
                ],
            ]);
    }

    public function test_can_export_full_tenant_snapshot(): void
    {
        $response = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/snapshot');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('format', 'siupk-desktop-snapshot-v1')
            ->assertJsonPath('type', 'full')
            ->assertJsonPath('tenant.code', 'tenant-a')
            ->assertJsonStructure([
                'status',
                'format',
                'type',
                'generated_at',
                'tenant' => ['id', 'code', 'name', 'status'],
                'meta' => [
                    'total_tables',
                    'total_records',
                    'table_counts',
                    'checksum',
                    'tables_order',
                ],
                'data' => [
                    'tenant_registry',
                    'accounts',
                ],
            ]);

        $json = $response->json();
        $this->assertNotEmpty($json['meta']['checksum']);
        $this->assertSame(64, strlen((string) $json['meta']['checksum']));
        $this->assertGreaterThan(0, $json['meta']['total_tables']);
    }

    public function test_can_export_delta_snapshot(): void
    {
        $response = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/delta?since=2026-01-01T00:00:00Z');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('type', 'delta')
            ->assertJsonPath('tenant.code', 'tenant-a');
    }

    public function test_delta_endpoint_validates_since_parameter(): void
    {
        $response = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/delta');

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_returns_404_for_unknown_tenant(): void
    {
        $response = $this->getJson('/api/v1/desktop/sync/tenants/non-existent-tenant/snapshot');

        $response->assertStatus(404)
            ->assertJsonPath('status', 'error');
    }

    public function test_enforces_api_key_authentication_when_configured(): void
    {
        Config::set('services.desktop.api_key', 'test-desktop-secret-token');

        // Unauthenticated request should return 401
        $unauthResponse = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/status');
        $unauthResponse->assertStatus(401)
            ->assertJsonPath('status', 'error');

        // Authenticated request with Bearer token should pass
        $authResponse = $this->withHeader('Authorization', 'Bearer test-desktop-secret-token')
            ->getJson('/api/v1/desktop/sync/tenants/tenant-a/status');
        $authResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // Authenticated request with custom header X-Desktop-Key should pass
        $headerResponse = $this->withHeader('X-Desktop-Key', 'test-desktop-secret-token')
            ->getJson('/api/v1/desktop/sync/tenants/tenant-a/status');
        $headerResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // Authenticated request with custom header X-API-Key should pass
        $xApiKeyResponse = $this->withHeader('X-API-Key', 'test-desktop-secret-token')
            ->getJson('/api/v1/desktop/sync/tenants/tenant-a/status');
        $xApiKeyResponse->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_rejects_query_parameter_api_token(): void
    {
        Config::set('services.desktop.api_key', 'test-desktop-secret-token');

        $queryApiKeyResponse = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/status?api_key=test-desktop-secret-token');
        $queryApiKeyResponse->assertStatus(401)
            ->assertJsonPath('status', 'error');

        $queryDesktopKeyResponse = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/status?desktop_key=test-desktop-secret-token');
        $queryDesktopKeyResponse->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_requires_desktop_sync_api_key_in_production_environment(): void
    {
        Config::set('services.desktop.api_key', '');
        $this->app['env'] = 'production';

        $response = $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/status');

        $response->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Unauthorized. DESKTOP_SYNC_API_KEY must be configured in production.');
    }
}
