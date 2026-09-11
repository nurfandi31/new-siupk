<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Invoice;
use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Services\Billing\InvoicePaymentService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubscriptionAutomationAndEnforcementTest extends TestCase
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

    public function test_generate_subscription_invoices_command_creates_invoices_for_expiring_subscriptions(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('sub_test_1');

        $plan = Plan::query()->create([
            'code' => 'basic',
            'name' => 'Paket Basic',
            'billing_period' => 'monthly',
            'price_amount' => 250000,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => 'active',
            'starts_at' => now()->subDays(25)->toDateString(),
            'ends_at' => now()->addDays(5)->toDateString(),
            'auto_renew' => true,
        ]);

        $this->artisan('subscriptions:generate-invoices', ['--days' => 7])
            ->assertSuccessful();

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->row_id,
            'subscription_id' => $subscription->row_id,
            'status' => 'issued',
            'amount' => 250000,
        ], 'platform');
    }

    public function test_check_overdue_command_suspends_tenant_subscription(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('sub_test_2');

        $plan = Plan::query()->create([
            'code' => 'basic',
            'name' => 'Paket Basic',
            'billing_period' => 'monthly',
            'price_amount' => 250000,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => 'active',
            'starts_at' => now()->subDays(40)->toDateString(),
            'ends_at' => now()->subDays(10)->toDateString(),
            'auto_renew' => true,
        ]);

        Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-TEST-OVERDUE',
            'tenant_id' => $tenant->row_id,
            'subscription_id' => $subscription->row_id,
            'purpose' => 'subscription',
            'status' => 'issued',
            'amount' => 250000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'due_at' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('subscriptions:check-overdue', ['--grace-days' => 3])
            ->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('suspended', $subscription->status);
    }

    public function test_overdue_suspended_tenant_is_blocked_from_operational_routes(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('sub_test_3');

        $plan = Plan::query()->create([
            'code' => 'basic',
            'name' => 'Paket Basic',
            'billing_period' => 'monthly',
            'price_amount' => 250000,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => 'suspended',
            'starts_at' => now()->subDays(40)->toDateString(),
            'ends_at' => now()->subDays(10)->toDateString(),
            'auto_renew' => true,
        ]);

        // Coba akses dashboard operasional -> diredirect ke billing
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('billing.invoices.index'));

        // Akses menu billing -> tetap diizinkan
        $this->actingAs($user)
            ->get('/billing/invoices')
            ->assertOk();
    }

    public function test_paid_invoice_renews_subscription_and_restores_active_status(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('sub_test_4');

        $plan = Plan::query()->create([
            'code' => 'basic',
            'name' => 'Paket Basic',
            'billing_period' => 'monthly',
            'price_amount' => 250000,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => 'suspended',
            'starts_at' => now()->subDays(40)->toDateString(),
            'ends_at' => now()->subDays(10)->toDateString(),
            'auto_renew' => true,
        ]);

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-RENEWAL-1',
            'tenant_id' => $tenant->row_id,
            'subscription_id' => $subscription->row_id,
            'purpose' => 'subscription',
            'status' => 'overdue',
            'amount' => 250000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'due_at' => now()->subDays(5)->toDateString(),
        ]);

        $invoicePaymentService = app(InvoicePaymentService::class);
        $invoicePaymentService->recordManual($invoice, [
            'amount' => 250000,
            'paid_at' => now(),
            'notes' => 'Pembayaran lunas via transfer',
        ], $user);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->ends_at->isFuture());
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createTenantWithUser(string $code): array
    {
        $shard = DatabaseShard::query()->firstOrCreate(
            ['code' => 'local'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Local Shard',
                'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
                'host' => 'mysql',
                'port' => 3306,
                'database_name' => (string) config('database.connections.tenant.database'),
                'credential_reference' => 'local',
                'placement_type' => 'shared',
                'status' => 'active',
            ],
        );

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => strtoupper($code).' Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['localhost']],
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Billing User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'bill_'.Str::lower(Str::random(8)),
            'password' => 'password',
            'status' => 'active',
            'tenant_id' => $tenant->row_id,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        config(['tenancy.local_tenant' => $code]);

        return [$user, $tenant];
    }
}
