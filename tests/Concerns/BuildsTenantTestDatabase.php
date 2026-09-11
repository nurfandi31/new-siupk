<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait BuildsTenantTestDatabase
{
    protected Tenant $testTenant;

    protected DatabaseShard $testShard;

    protected TenantPlacement $testPlacement;

    protected function rebuildTenantTestDatabases(): void
    {
        if (! filter_var(env('RUN_TENANCY_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_TENANCY_INTEGRATION_TESTS=true to run tenancy tests.');
        }

        $platformDb = (string) config('database.connections.platform.database');
        $tenantDb = (string) config('database.connections.tenant.database');
        foreach ([$platformDb, $tenantDb] as $database) {
            if (! is_string($database) || (! str_ends_with($database, '_test') && ! str_contains($database, 'test') && $database !== ':memory:')) {
                throw new \RuntimeException('Integration tests require databases ending with _test or containing test.');
            }
        }

        DB::connection('platform')->disconnect();
        DB::connection('tenant')->disconnect();

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);

        $this->testTenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->testShard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'test-shard',
            'name' => 'Test Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => (int) config('database.connections.tenant.port', 3306),
            'database_name' => (string) config('database.connections.tenant.database'),
            'credential_reference' => 'test',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $this->testPlacement = TenantPlacement::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'shard_id' => $this->testShard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $this->testTenant->row_id,
            'public_id' => $this->testTenant->public_id,
            'code' => $this->testTenant->code,
            'name' => $this->testTenant->name,
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TenantContext::class)->initialize(
            $this->testTenant,
            $this->testPlacement,
            $this->testShard,
        );
    }

    protected function clearTenantTestContext(): void
    {
        app(TenantContext::class)->clear();
    }
}
