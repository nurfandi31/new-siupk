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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class XenditPaymentIntegrationTest extends TestCase
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

    public function test_user_can_initiate_xendit_payment(): void
    {
        [$user, $tenant, $invoice] = $this->createTenantInvoice('tenant_xendit_1');

        config([
            'xendit.secret_key' => 'xnd_development_test_key_123',
            'xendit.mode' => 'sandbox',
            'billing.active_gateway' => 'xendit',
        ]);

        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response([
                'id' => 'xendit_inv_001',
                'external_id' => 'XENDIT-REF-001',
                'invoice_url' => 'https://checkout.xendit.co/web/xendit_inv_001',
                'amount' => 250000,
                'status' => 'PENDING',
                'expiry_date' => date('c', time() + 86400),
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->post("/billing/invoices/{$invoice->row_id}/pay", [
                'gateway' => 'xendit',
                'payment_method' => 'QRIS',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->row_id,
            'method' => 'xendit',
            'status' => 'pending',
        ], 'platform');
    }

    public function test_xendit_webhook_callback_processes_paid_invoice(): void
    {
        [$user, $tenant, $invoice] = $this->createTenantInvoice('tenant_xendit_2');

        $secretKey = 'xnd_development_test_key_123';
        $externalId = 'XENDIT-ORDER-99';
        $xenditId = 'xendit_inv_99';

        config([
            'xendit.secret_key' => $secretKey,
            'xendit.callback_token' => 'xendit_callback_token_secret',
        ]);

        $invoice->payments()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'method' => 'xendit',
            'status' => 'pending',
            'amount' => 250000,
            'reference' => $externalId,
            'tripay_reference' => $xenditId,
        ]);

        $response = $this->withHeaders([
            'x-callback-token' => 'xendit_callback_token_secret',
        ])->postJson('/xendit/callback', [
            'id' => $xenditId,
            'external_id' => $externalId,
            'status' => 'PAID',
            'paid_amount' => 250000,
            'payment_method' => 'QRIS',
            'payment_channel' => 'SHOOPEPAY',
        ]);

        $response->assertOk();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEqualsWithDelta(250000.0, (float) $invoice->amount_paid, 0.02);
    }

    public function test_admin_can_update_xendit_credentials_and_switch_gateway(): void
    {
        $admin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'email' => 'superadmin@example.test',
            'username' => 'superadmin',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post('/admin/integrations/xendit', [
                'secret_key' => 'xnd_development_new_secret',
                'public_key' => 'xnd_public_new_key',
                'callback_token' => 'new_cb_token',
                'mode' => 'sandbox',
                'default_method' => 'QRIS',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switchResponse = $this->actingAs($admin)
            ->post('/admin/integrations/active-gateway', [
                'gateway' => 'xendit',
            ]);

        $switchResponse->assertRedirect();
        $switchResponse->assertSessionHas('success');
    }

    /**
     * @return array{0: User, 1: Tenant, 2: Invoice}
     */
    private function createTenantInvoice(string $code): array
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
            'name' => 'Billing Admin',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'admin_'.Str::lower(Str::random(8)),
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

        $plan = Plan::query()->create([
            'code' => 'standard',
            'name' => 'Paket Standar',
            'billing_period' => 'monthly',
            'price_amount' => 250000,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'auto_renew' => true,
        ]);

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-TEST-'.Str::upper(Str::random(6)),
            'tenant_id' => $tenant->row_id,
            'subscription_id' => $subscription->row_id,
            'purpose' => 'subscription',
            'status' => 'issued',
            'amount' => 250000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'due_at' => now()->addDays(7)->toDateString(),
        ]);

        config(['tenancy.local_tenant' => $code]);

        return [$user, $tenant, $invoice];
    }
}
