<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Billing\Services\SubscriptionGateService;
use App\Domain\Sync\Services\DesktopSyncClientService;
use App\Models\Platform\Invoice;
use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SubscriptionGateSyncTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private string $invoiceNumber = 'INV-SYNC-GATE';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_service_blocks_when_blocking_invoice_exists(): void
    {
        $invoice = $this->createInvoice([
            'number' => 'INV-SYNC-BLOCK',
            'description' => 'Tagihan operasional',
            'blocks_access' => true,
        ]);

        $gate = $this->gate();

        $this->assertTrue($gate['blocked']);
        $this->assertSame('invoice_block', $gate['reason']);
        $this->assertSame($invoice->row_id, $gate['invoice_id']);
        $this->assertSame('INV-SYNC-BLOCK', $gate['invoice_number']);
    }

    public function test_service_blocks_when_subscription_is_suspended(): void
    {
        $this->createSubscription('suspended');

        $gate = $this->gate();

        $this->assertTrue($gate['blocked']);
        $this->assertSame('subscription_suspended', $gate['reason']);
        $this->assertNull($gate['invoice_id']);
        $this->assertNull($gate['invoice_number']);
    }

    public function test_service_allows_active_subscription_without_blocking_invoice(): void
    {
        $this->createSubscription('active');
        $this->createInvoice(['blocks_access' => false]);

        $gate = $this->gate();

        $this->assertFalse($gate['blocked']);
        $this->assertNull($gate['reason']);
        $this->assertNull($gate['invoice_id']);
        $this->assertNull($gate['invoice_number']);
        $this->assertNull($gate['message']);
    }

    public function test_sync_status_returns_subscription_gate(): void
    {
        $this->createInvoice([
            'number' => 'INV-SYNC-STATUS',
            'blocks_access' => true,
        ]);

        $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/status')
            ->assertOk()
            ->assertJsonPath('subscription.blocked', true)
            ->assertJsonPath('subscription.reason', 'invoice_block')
            ->assertJsonPath('subscription.invoice_number', 'INV-SYNC-STATUS');
    }

    public function test_desktop_push_is_blocked_before_applying_mutations(): void
    {
        $this->createInvoice(['blocks_access' => true]);
        $before = $this->mutationCounters();
        $mutation = $this->masterMutation();

        $this->push([$mutation])
            ->assertStatus(402)
            ->assertJsonPath('status', 'blocked')
            ->assertJsonPath('code', 'SUBSCRIPTION_BLOCKED')
            ->assertJsonPath('invoice_number', $this->invoiceNumber);

        $this->assertDatabaseHas('business_types', [
            'id' => 11,
            'name' => 'Cloud Master',
        ], 'tenant');
        $this->assertSame($before['outbox'], $this->mutationCounters()['outbox']);
        $this->assertSame($before['conflicts'], $this->mutationCounters()['conflicts']);
        $this->assertDatabaseMissing('sync_mutations', ['mutation_uuid' => $mutation['mutation_uuid']], 'tenant');
    }

    public function test_desktop_push_runs_normally_when_subscription_is_active(): void
    {
        $this->createSubscription('active');
        $mutation = $this->masterMutation();

        $this->push([$mutation])
            ->assertOk()
            ->assertJsonPath('accepted.0', $mutation['mutation_uuid']);

        $this->assertDatabaseHas('business_types', [
            'id' => 11,
            'name' => 'Offline Master',
        ], 'tenant');
    }

    public function test_desktop_client_stops_before_push_or_pull_when_gate_is_blocked(): void
    {
        Http::fake([
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/status' => Http::response([
                'status' => 'success',
                'subscription' => [
                    'blocked' => true,
                    'reason' => 'invoice_block',
                    'invoice_number' => 'INV-CLIENT-GATE',
                    'message' => 'Sync ditahan sampai tagihan dibayar.',
                ],
            ]),
            'https://app.siupk.id/api/v1/desktop/sync/tenants/tenant-a/snapshot' => Http::response([
                'status' => 'success',
                'format' => 'siupk-desktop-snapshot-v1',
                'data' => [],
            ]),
        ]);
        Config::set('desktop.server.url', 'https://app.siupk.id');
        Config::set('desktop.server.tenant_code', 'tenant-a');

        $result = app(DesktopSyncClientService::class)->syncFromCloud('tenant-a');

        $this->assertSame('blocked', $result['status']);
        $this->assertTrue($result['blocked']);
        $this->assertSame('INV-CLIENT-GATE', $result['invoice_number']);
        Http::assertSentCount(1);
    }

    private function gate(): array
    {
        return app(SubscriptionGateService::class)->check($this->testTenant->row_id);
    }

    private function createInvoice(array $attributes = []): Invoice
    {
        $this->invoiceNumber = $attributes['number'] ?? 'INV-SYNC-GATE';

        return Invoice::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'number' => $this->invoiceNumber,
            'tenant_id' => $this->testTenant->row_id,
            'purpose' => 'support',
            'status' => 'issued',
            'amount' => 500000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'blocks_access' => false,
        ], $attributes));
    }

    private function createSubscription(string $status): Subscription
    {
        $plan = Plan::query()->create([
            'code' => 'PLAN-'.Str::upper(Str::random(6)),
            'name' => 'Test Plan',
            'features' => null,
            'is_active' => true,
        ]);

        return Subscription::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => $status,
            'starts_at' => now()->toDateString(),
        ]);
    }

    private function mutationCounters(): array
    {
        return [
            'outbox' => DB::connection('tenant')->table('outbox')->count(),
            'conflicts' => DB::connection('tenant')->table('sync_conflicts')->count(),
        ];
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
        DB::connection('tenant')->table('business_types')->updateOrInsert(
            ['id' => 11],
            $this->businessTypeRow(),
        );

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
            'name' => 'Cloud Master',
            'description' => null,
            'is_active' => true,
            'created_at' => '2026-09-02 08:00:00',
            'updated_at' => '2026-09-02 08:00:00',
        ], $overrides);
    }
}
