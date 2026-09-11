<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Domain\Sync\Services\DesktopOutboxService;
use App\Models\Tenant\BusinessType;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class DesktopPushTest extends TestCase
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

    public function test_master_mutation_uses_last_write_wins(): void
    {
        $payload = $this->masterMutation();

        $response = $this->push([$payload]);

        $response->assertOk()->assertJsonPath('accepted.0', $payload['mutation_uuid']);
        $this->assertTenantDatabaseHas('business_types', ['id' => 11, 'name' => 'Offline Master']);
    }

    public function test_sensitive_server_newer_row_becomes_conflict(): void
    {
        DB::connection('tenant')->table('accounts')->insert($this->accountRow([
            'updated_at' => '2026-09-02 10:00:00',
        ]));

        $mutation = [
            'mutation_uuid' => (string) Str::uuid(),
            'table_name' => 'accounts',
            'operation' => 'update',
            'row_public_id' => 21,
            'payload' => array_merge($this->accountRow(), ['name' => 'Offline Account', 'updated_at' => '2026-09-02 09:00:00']),
            'client_updated_at' => '2026-09-02 09:00:00',
        ];

        $response = $this->push([$mutation]);

        $response->assertOk()->assertJsonPath('conflicts.0.mutation_uuid', $mutation['mutation_uuid']);
        $this->assertTenantDatabaseHas('accounts', ['id' => 21, 'name' => 'Cloud Account']);
        $this->assertTenantDatabaseHas('sync_conflicts', ['table_name' => 'accounts', 'reason' => 'server_wins']);
    }

    public function test_mutation_uuid_is_idempotent(): void
    {
        $mutation = $this->masterMutation();

        $this->push([$mutation])->assertOk();
        $this->push([$mutation])->assertOk();

        $this->assertSame(1, DB::connection('tenant')->table('business_types')->where('id', 11)->count());
        $this->assertSame(1, DB::connection('tenant')->table('sync_mutations')->where('mutation_uuid', $mutation['mutation_uuid'])->count());
        $this->assertSame(1, DB::connection('tenant')->table('audit_logs')->where('auditable_type', 'business_types')->count());
    }

    public function test_delete_against_changed_cloud_record_is_conflict(): void
    {
        DB::connection('tenant')->table('business_types')->insert(array_merge($this->businessTypeRow(), [
            'name' => 'Cloud Master',
            'updated_at' => '2026-09-02 10:00:00',
        ]));

        $mutation = [
            'mutation_uuid' => (string) Str::uuid(),
            'table_name' => 'business_types',
            'operation' => 'delete',
            'row_public_id' => 11,
            'payload' => $this->businessTypeRow(['updated_at' => '2026-09-02 09:00:00']),
            'client_updated_at' => '2026-09-02 09:00:00',
        ];

        $response = $this->push([$mutation]);

        $response->assertOk();
        $this->assertSame(1, DB::connection('tenant')->table('business_types')->where('id', 11)->count());
        $this->assertSame('delete_conflict', $response->json('conflicts.0.reason'));
        $this->assertTenantDatabaseHas('business_types', ['id' => 11, 'name' => 'Cloud Master']);
        $this->assertTenantDatabaseHas('sync_conflicts', ['table_name' => 'business_types', 'reason' => 'delete_conflict']);
    }

    public function test_outbox_observer_uses_desktop_connection_and_ignores_server_connection(): void
    {
        $type = new BusinessType(['code' => 'OBS', 'name' => 'Observed', 'is_active' => true]);
        $type->setConnection('tenant');
        $type->save();

        $this->assertSame(0, DB::connection('tenant')->table('outbox')->where('table_name', 'business_types')->count());

        Config::set('database.default', 'desktop_local');
        Config::set('database.connections.desktop_local', ['driver' => 'sqlite', 'database' => database_path('desktop_outbox_test.sqlite')]);
        File::delete(database_path('desktop_outbox_test.sqlite'));
        File::put(database_path('desktop_outbox_test.sqlite'), '');
        Schema::connection('desktop_local')->create('outbox', function ($table): void {
            $table->bigIncrements('id');
            $table->uuid('mutation_uuid');
            $table->string('table_name');
            $table->string('operation');
            $table->string('row_public_id');
            $table->json('payload');
            $table->dateTime('created_at');
            $table->dateTime('pushed_at')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
        });
        DB::purge('desktop_local');
        DB::reconnect('desktop_local');

        $serverType = new BusinessType(['code' => 'SERVER', 'name' => 'Server', 'is_active' => true]);
        $serverType->setAttribute('tenant_id', 1);
        $serverType->setAttribute('id', 12);
        $serverType->setConnection('tenant');
        $serverType->save();

        $this->assertSame(1, DB::connection('desktop_local')->table('outbox')->where('table_name', 'business_types')->count());
        $this->assertSame(0, DB::connection('tenant')->table('outbox')->where('table_name', 'business_types')->count());
    }

    public function test_client_flush_sends_pending_mutations_and_marks_synced(): void
    {
        $mutationUuid = (string) Str::uuid();
        DB::connection('tenant')->table('outbox')->insert([
            'mutation_uuid' => $mutationUuid,
            'table_name' => 'business_types',
            'operation' => 'insert',
            'row_public_id' => '11',
            'payload' => json_encode($this->businessTypeRow()),
            'created_at' => now(),
            'status' => 'pending',
            'attempts' => 0,
        ]);

        Http::fake([
            '*/api/v1/desktop/sync/tenants/tenant-a/push' => Http::response([
                'accepted' => [$mutationUuid],
                'conflicts' => [],
                'rejected' => [],
            ]),
        ]);

        $result = app(DesktopOutboxService::class)->flushPendingMutations('tenant-a', 'tenant');

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, DB::connection('tenant')->table('outbox')->where('status', 'synced')->count());
    }

    private function push(array $mutations): TestResponse
    {
        return $this->postJson('/api/v1/desktop/sync/tenants/tenant-a/push', [
            'mutations' => $mutations,
            'last_pulled_at' => '2026-09-01T00:00:00Z',
        ]);
    }

    private function masterMutation(): array
    {
        return [
            'mutation_uuid' => (string) Str::uuid(),
            'table_name' => 'business_types',
            'operation' => 'update',
            'row_public_id' => 11,
            'payload' => $this->businessTypeRow(['name' => 'Offline Master']),
            'client_updated_at' => '2026-09-02T10:00:00Z',
        ];
    }

    private function businessTypeRow(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => 1,
            'id' => 11,
            'code' => 'OFFLINE',
            'name' => 'Offline',
            'description' => null,
            'is_active' => true,
            'created_at' => '2026-09-02 08:00:00',
            'updated_at' => '2026-09-02 08:00:00',
        ], $overrides);
    }

    private function accountRow(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => 1,
            'id' => 21,
            'public_id' => (string) Str::ulid(),
            'code' => '1-0000',
            'name' => 'Cloud Account',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'parent_row_id' => null,
            'level' => 1,
            'is_postable' => true,
            'is_active' => true,
            'deactivated_at' => null,
            'legacy_parent_code' => null,
            'created_at' => '2026-09-02 08:00:00',
            'updated_at' => '2026-09-02 08:00:00',
        ], $overrides);
    }

    private function assertTenantDatabaseHas(string $table, array $conditions): void
    {
        $this->assertDatabaseHas($table, $conditions, 'tenant');
    }
}
