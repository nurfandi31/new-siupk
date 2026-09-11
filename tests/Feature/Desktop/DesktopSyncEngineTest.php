<?php

declare(strict_types=1);

namespace Tests\Feature\Desktop;

use App\Domain\Sync\Services\DesktopSnapshotIngestionService;
use App\Domain\Sync\Services\DesktopSyncClientService;
use App\Domain\Sync\Services\TenantSnapshotService;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class DesktopSyncEngineTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private string $testDesktopSqlitePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();

        $this->testDesktopSqlitePath = database_path('test_desktop_sync.sqlite');
        if (File::exists($this->testDesktopSqlitePath)) {
            File::delete($this->testDesktopSqlitePath);
        }
        File::put($this->testDesktopSqlitePath, '');

        Config::set('desktop.sqlite_database', $this->testDesktopSqlitePath);
        Config::set('database.connections.desktop_local', [
            'driver' => 'sqlite',
            'database' => $this->testDesktopSqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $prevConnection = config('tenancy.tenant_connection');
        Config::set('tenancy.tenant_connection', 'desktop_local');

        Artisan::call('migrate', [
            '--database' => 'desktop_local',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);

        Config::set('tenancy.tenant_connection', $prevConnection);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testDesktopSqlitePath)) {
            File::delete($this->testDesktopSqlitePath);
        }
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_ingestion_service_ingests_full_snapshot_to_sqlite(): void
    {
        $tenant = Tenant::query()->where('code', 'tenant-a')->firstOrFail();
        $snapshotService = app(TenantSnapshotService::class);
        $snapshot = $snapshotService->export($tenant);

        $this->assertGreaterThan(0, $snapshot['meta']['total_records']);

        $ingestionService = app(DesktopSnapshotIngestionService::class);
        $result = $ingestionService->ingest($snapshot, 'desktop_local');

        $this->assertSame('success', $result['status']);
        $this->assertSame('full', $result['type']);
        $this->assertGreaterThan(0, $result['total_records']);

        // Verify records in local desktop SQLite
        $this->assertSame(1, DB::connection('desktop_local')->table('tenant_registry')->count());
        $this->assertGreaterThan(0, DB::connection('desktop_local')->table('accounts')->count());
    }

    public function test_ingestion_service_validates_checksum_mismatch(): void
    {
        $tenant = Tenant::query()->where('code', 'tenant-a')->firstOrFail();
        $snapshotService = app(TenantSnapshotService::class);
        $snapshot = $snapshotService->export($tenant);

        // Corrupt data without updating checksum
        $snapshot['data']['tenant_registry'][0]['name'] = 'Corrupted Tenant Name';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('checksum mismatch');

        $ingestionService = app(DesktopSnapshotIngestionService::class);
        $ingestionService->ingest($snapshot, 'desktop_local');
    }

    public function test_desktop_sync_client_handles_successful_sync(): void
    {
        $tenant = Tenant::query()->where('code', 'tenant-a')->firstOrFail();
        $snapshot = app(TenantSnapshotService::class)->export($tenant);

        Http::fake([
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/status' => Http::response(['status' => 'success', 'server_time' => now()->toIso8601String(), 'subscription' => ['blocked' => false]], 200),
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/snapshot' => Http::response($snapshot, 200),
        ]);

        Config::set('desktop.server.url', 'https://app.siupk.id');
        Config::set('desktop.server.tenant_code', 'tenant-a');
        Config::set('tenancy.tenant_connection', 'desktop_local');

        $syncClient = app(DesktopSyncClientService::class);
        $result = $syncClient->syncFromCloud('tenant-a');

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, DB::connection('desktop_local')->table('tenant_registry')->count());
    }

    public function test_desktop_sync_command_runs_successfully(): void
    {
        $tenant = Tenant::query()->where('code', 'tenant-a')->firstOrFail();
        $snapshot = app(TenantSnapshotService::class)->export($tenant);

        Http::fake([
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/status' => Http::response(['status' => 'success', 'server_time' => now()->toIso8601String(), 'subscription' => ['blocked' => false]], 200),
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/snapshot' => Http::response($snapshot, 200),
        ]);

        Config::set('desktop.server.url', 'https://app.siupk.id');
        Config::set('tenancy.tenant_connection', 'desktop_local');

        $this->artisan('desktop:sync', ['--tenant' => 'tenant-a'])
            ->expectsOutputToContain('Synchronization completed successfully!')
            ->assertSuccessful();
    }

    public function test_desktop_client_controller_endpoints(): void
    {
        $tenant = Tenant::query()->where('code', 'tenant-a')->firstOrFail();
        $snapshot = app(TenantSnapshotService::class)->export($tenant);

        Http::fake([
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/status' => Http::response(['status' => 'success', 'server_time' => now()->toIso8601String(), 'subscription' => ['blocked' => false]], 200),
            'https://app.siupk.id/api/v1/desktop/sync/status' => Http::response(['status' => 'success', 'server_time' => now()->toIso8601String()], 200),
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/snapshot' => Http::response($snapshot, 200),
        ]);

        Config::set('desktop.server.url', 'https://app.siupk.id');
        Config::set('desktop.server.tenant_code', 'tenant-a');
        Config::set('tenancy.tenant_connection', 'desktop_local');

        // Test status endpoint
        $statusResponse = $this->getJson('/desktop/sync/status');
        $statusResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('server_online', true);

        // Test trigger endpoint
        $triggerResponse = $this->postJson('/desktop/sync/trigger', ['tenant' => 'tenant-a']);
        $triggerResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Data successfully synchronized from cloud server.');
    }
}
