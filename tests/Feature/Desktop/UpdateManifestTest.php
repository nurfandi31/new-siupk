<?php

declare(strict_types=1);

namespace Tests\Feature\Desktop;

use App\Domain\Desktop\Services\UpdateManifestService;
use App\Models\Platform\Invoice;
use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use App\Models\Platform\Tenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class UpdateManifestTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();
        $this->tenant = $this->testTenant;

        config([
            'desktop-update.latest_version' => '1.2.0',
            'desktop-update.min_version' => '1.1.0',
            'desktop-update.download_url' => 'https://github.com/its-enpii/new_siupk/releases/latest/download/siupk-Setup.exe',
            'desktop-update.release_notes_url' => 'https://github.com/its-enpii/new_siupk/releases/latest',
            'desktop-update.sha512' => 'test-sha512',
        ]);
    }

    public function test_update_check_reports_available_update_and_manifest(): void
    {
        $this->createSubscription('active');

        $this->getJson('/api/v1/desktop/sync/update/check?platform=win&current_version=1.1.0')
            ->assertOk()
            ->assertJson([
                'update_available' => true,
                'latest_version' => '1.2.0',
                'current_version' => '1.1.0',
                'min_supported_version' => '1.1.0',
                'force_update' => false,
                'download_url' => 'https://github.com/its-enpii/new_siupk/releases/latest/download/siupk-Setup.exe',
                'release_notes_url' => 'https://github.com/its-enpii/new_siupk/releases/latest',
                'sha512' => 'test-sha512',
                'subscription' => [
                    'blocked' => false,
                ],
            ]);
    }

    public function test_update_check_detects_force_update_for_outdated_minimum(): void
    {
        $this->createSubscription('active');

        $this->getJson('/api/v1/desktop/sync/update/check?current_version=1.0.0')
            ->assertOk()
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('force_update', true);
    }

    public function test_update_check_reports_no_update_when_current_is_latest(): void
    {
        $this->createSubscription('active');

        $this->getJson('/api/v1/desktop/sync/update/check?current_version=1.2.0')
            ->assertOk()
            ->assertJsonPath('update_available', false)
            ->assertJsonPath('force_update', false);
    }

    public function test_sync_status_contains_configured_server_version_and_update_summary(): void
    {
        config(['desktop-update.server_version' => '2.3.4']);
        $this->createSubscription('active');

        $this->getJson('/api/v1/desktop/sync/tenants/tenant-a/status?current_version=1.0.0')
            ->assertOk()
            ->assertJsonPath('app_version', '2.3.4')
            ->assertJsonPath('update.update_available', true)
            ->assertJsonPath('update.latest_version', '1.2.0')
            ->assertJsonPath('update.force_update', true);
    }

    public function test_desktop_push_rejects_old_client_with_header(): void
    {
        $this->createSubscription('active');
        DB::connection('tenant')->table('business_types')->insert([
            'id' => 11,
            'tenant_id' => 1,
            'code' => 'OUTDATED',
            'name' => 'Old Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('X-App-Version', '1.0.0')
            ->postJson('/api/v1/desktop/sync/tenants/tenant-a/push', [
                'mutations' => [
                    [
                        'mutation_uuid' => (string) Str::uuid(),
                        'table_name' => 'business_types',
                        'operation' => 'update',
                        'row_public_id' => 11,
                        'payload' => ['id' => 11, 'name' => 'Blocked Client'],
                        'client_updated_at' => '2026-09-02T10:00:00Z',
                    ],
                ],
                'last_pulled_at' => null,
            ])
            ->assertStatus(426)
            ->assertJsonPath('code', 'CLIENT_OUTDATED')
            ->assertJsonPath('min_supported_version', '1.1.0');

        $this->assertDatabaseHas('business_types', [
            'id' => 11,
            'name' => 'Old Client',
        ], 'tenant');
    }

    public function test_desktop_push_runs_normally_without_version_header(): void
    {
        $this->createSubscription('active');
        $mutation = [
            'mutation_uuid' => (string) Str::uuid(),
            'table_name' => 'business_types',
            'operation' => 'update',
            'row_public_id' => 11,
            'payload' => ['id' => 11],
            'client_updated_at' => '2026-09-02T10:00:00Z',
        ];

        $this->postJson('/api/v1/desktop/sync/tenants/tenant-a/push', [
            'mutations' => [$mutation],
        ])->assertOk();
    }

    public function test_update_check_carries_subscription_block(): void
    {
        $this->createSubscription('active');
        $this->createInvoice(['blocks_access' => true, 'number' => 'INV-UPDATE-GATE']);

        $this->getJson('/api/v1/desktop/sync/update/check?current_version=1.2.0')
            ->assertOk()
            ->assertJsonPath('subscription.blocked', true)
            ->assertJsonPath('subscription.reason', 'invoice_block')
            ->assertJsonPath('subscription.invoice_number', 'INV-UPDATE-GATE');
    }

    public function test_manifest_service_normalizes_and_compares_versions(): void
    {
        $manifest = app(UpdateManifestService::class)->manifest(' v1.1.0 ');

        $this->assertSame('1.1.0', $manifest['current_version']);
        $this->assertTrue($manifest['update_available']);
        $this->assertFalse($manifest['force_update']);
    }

    private function createSubscription(string $status): void
    {
        $plan = Plan::query()->create([
            'code' => 'PLAN-'.Str::upper(Str::random(6)),
            'name' => 'Test Plan',
            'features' => null,
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'tenant_id' => $this->tenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => $status,
            'starts_at' => now()->toDateString(),
        ]);
    }

    private function createInvoice(array $attributes): void
    {
        Invoice::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-UPDATE',
            'tenant_id' => $this->tenant->row_id,
            'purpose' => 'support',
            'status' => 'issued',
            'amount' => 500000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'blocks_access' => false,
        ], $attributes));
    }
}
