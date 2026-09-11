<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Membership\Models\Person;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_local_ids_restart_per_tenant_and_queries_are_scoped(): void
    {
        $tenantARecord = Person::query()->create([
            'full_name' => 'Person A',
        ]);

        self::assertSame(1, (int) $tenantARecord->id);
        self::assertSame((int) $this->testTenant->row_id, (int) $tenantARecord->tenant_id);
        self::assertCount(1, Person::query()->get());

        $tenantB = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $placementB = TenantPlacement::query()->create([
            'tenant_id' => $tenantB->row_id,
            'shard_id' => $this->testShard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $tenantB->row_id,
            'public_id' => $tenantB->public_id,
            'code' => $tenantB->code,
            'name' => $tenantB->name,
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TenantContext::class)->clear();
        app(TenantContext::class)->initialize($tenantB, $placementB, $this->testShard);

        $tenantBRecord = Person::query()->create([
            'full_name' => 'Person B',
        ]);

        self::assertSame(1, (int) $tenantBRecord->id);
        self::assertCount(1, Person::query()->get());
        self::assertSame('Person B', Person::query()->firstOrFail()->full_name);
    }

    public function test_explicit_cross_tenant_insert_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        Person::query()->create([
            'tenant_id' => $this->testTenant->row_id + 999,
            'full_name' => 'Invalid Tenant Person',
        ]);
    }
}
