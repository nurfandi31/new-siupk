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

final class InAppPaymentChannelTest extends TestCase
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

    public function test_user_can_view_invoice_with_payment_channels(): void
    {
        [$user, $tenant, $invoice] = $this->createTenantInvoice('tenant_va_1');

        $response = $this->actingAs($user)
            ->get("/billing/invoices/{$invoice->row_id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Invoices/Show')
            ->has('channels')
            ->where('invoice.number', $invoice->number)
        );
    }

    public function test_user_can_initiate_bank_virtual_account_payment(): void
    {
        [$user, $tenant, $invoice] = $this->createTenantInvoice('tenant_va_2');

        config([
            'tripay.merchant_code' => 'T12345',
            'tripay.api_key' => 'test-api-key',
            'tripay.private_key' => 'test-private-key',
        ]);

        Http::fake([
            'https://tripay.co.id/api-sandbox/transaction/create' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'DEV-T12345-001',
                    'merchant_ref' => 'REF-001',
                    'payment_method' => 'BCAVA',
                    'payment_name' => 'BCA Virtual Account',
                    'pay_code' => '88081234567890',
                    'amount' => 250000,
                    'fee_customer' => 4000,
                    'total_amount' => 254000,
                    'expired_time' => time() + 86400,
                    'instructions' => [
                        [
                            'title' => 'Pembayaran via m-BCA (BCA Mobile)',
                            'steps' => [
                                'Pilih menu m-Transfer > BCA Virtual Account.',
                                'Masukkan nomor Virtual Account 88081234567890.',
                                'Konfirmasi dan masukkan PIN m-BCA.',
                            ],
                        ],
                    ],
                    'checkout_url' => 'https://tripay.co.id/checkout/DEV-T12345-001',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->post("/billing/invoices/{$invoice->row_id}/pay", [
                'payment_method' => 'BCAVA',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->row_id,
            'method' => 'tripay',
            'status' => 'pending',
            'tripay_reference' => 'DEV-T12345-001',
        ], 'platform');
    }

    public function test_user_can_check_status_and_sync_paid_invoice(): void
    {
        [$user, $tenant, $invoice] = $this->createTenantInvoice('tenant_va_3');

        config([
            'tripay.merchant_code' => 'T12345',
            'tripay.api_key' => 'test-api-key',
            'tripay.private_key' => 'test-private-key',
        ]);

        $invoice->payments()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'method' => 'tripay',
            'status' => 'pending',
            'amount' => 250000,
            'reference' => 'MERCHANT-REF-99',
            'tripay_reference' => 'DEV-T12345-SYNC',
            'tripay_payload' => [
                'merchant_ref' => 'MERCHANT-REF-99',
                'reference' => 'DEV-T12345-SYNC',
                'status' => 'UNPAID',
            ],
        ]);

        Http::fake([
            'https://tripay.co.id/api-sandbox/transaction/detail*' => Http::response([
                'success' => true,
                'data' => [
                    'merchant_ref' => 'MERCHANT-REF-99',
                    'reference' => 'DEV-T12345-SYNC',
                    'status' => 'PAID',
                    'amount_received' => 250000,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->post("/billing/invoices/{$invoice->row_id}/check-status");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEqualsWithDelta(250000.0, (float) $invoice->amount_paid, 0.02);
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
