<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Invoice;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Services\Billing\InvoicePaymentService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InvoiceBlockingTest extends TestCase
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

    public function test_superadmin_can_create_invoice_with_blocks_access_flag(): void
    {
        $superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'email' => 'superadmin@example.test',
            'username' => 'superadmin_block',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        [$tenantUser, $tenant] = $this->createTenantWithUser('block_tenant_1');

        $response = $this->actingAs($superadmin)->post('/admin/invoices', [
            'tenant_id' => $tenant->row_id,
            'purpose' => 'setup',
            'amount' => 500000,
            'currency' => 'IDR',
            'due_at' => now()->addDays(7)->toDateString(),
            'description' => 'Biaya Setup Server & Training',
            'status' => 'issued',
            'blocks_access' => true,
        ]);

        $response->assertRedirect();

        $invoice = Invoice::query()->where('tenant_id', $tenant->row_id)->first();
        $this->assertNotNull($invoice);
        $this->assertTrue((bool) $invoice->blocks_access);
        $this->assertSame('Biaya Setup Server & Training', $invoice->description);
    }

    public function test_superadmin_can_toggle_blocking_option_on_invoice(): void
    {
        $superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'email' => 'superadmin2@example.test',
            'username' => 'superadmin_toggle',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        [$tenantUser, $tenant] = $this->createTenantWithUser('block_tenant_2');

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-BLOCK-TOGGLE',
            'tenant_id' => $tenant->row_id,
            'purpose' => 'other',
            'status' => 'issued',
            'amount' => 300000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'blocks_access' => false,
        ]);

        $this->assertFalse((bool) $invoice->blocks_access);

        // Toggle to true
        $this->actingAs($superadmin)
            ->post("/admin/invoices/{$invoice->row_id}/toggle-blocking")
            ->assertRedirect();

        $invoice->refresh();
        $this->assertTrue((bool) $invoice->blocks_access);

        // Toggle back to false
        $this->actingAs($superadmin)
            ->post("/admin/invoices/{$invoice->row_id}/toggle-blocking")
            ->assertRedirect();

        $invoice->refresh();
        $this->assertFalse((bool) $invoice->blocks_access);
    }

    public function test_tenant_with_blocking_invoice_is_redirected_to_invoice_page_from_operational_routes(): void
    {
        [$tenantUser, $tenant] = $this->createTenantWithUser('block_tenant_3');

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-MUST-PAY',
            'tenant_id' => $tenant->row_id,
            'purpose' => 'support',
            'status' => 'issued',
            'amount' => 750000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'due_at' => now()->addDays(3)->toDateString(),
            'description' => 'Biaya Maintenance Tahunan',
            'blocks_access' => true,
        ]);

        // Accessing dashboard -> redirected directly to invoice show page
        $this->actingAs($tenantUser)
            ->get('/dashboard')
            ->assertRedirect(route('billing.invoices.show', $invoice->row_id));
    }

    public function test_tenant_with_blocking_invoice_receives_402_on_json_operational_requests(): void
    {
        [$tenantUser, $tenant] = $this->createTenantWithUser('block_tenant_4');

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-JSON-BLOCK',
            'tenant_id' => $tenant->row_id,
            'purpose' => 'other',
            'status' => 'issued',
            'amount' => 450000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'blocks_access' => true,
        ]);

        $response = $this->actingAs($tenantUser)
            ->getJson('/dashboard');

        $response->assertStatus(402);
        $response->assertJson([
            'status' => 'blocked',
            'invoice_id' => $invoice->row_id,
        ]);
    }

    public function test_tenant_with_blocking_invoice_can_still_access_billing_routes_and_logout(): void
    {
        [$tenantUser, $tenant] = $this->createTenantWithUser('block_tenant_5');

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-ALLOWED-BILLING',
            'tenant_id' => $tenant->row_id,
            'purpose' => 'other',
            'status' => 'issued',
            'amount' => 200000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'blocks_access' => true,
        ]);

        // Invoices index is allowed
        $this->actingAs($tenantUser)
            ->get('/billing/invoices')
            ->assertOk();

        // Invoice show is allowed
        $this->actingAs($tenantUser)
            ->get("/billing/invoices/{$invoice->row_id}")
            ->assertOk();

        // Profile is allowed
        $this->actingAs($tenantUser)
            ->get('/profile')
            ->assertOk();

        // Logout is allowed
        $this->actingAs($tenantUser)
            ->post('/logout')
            ->assertRedirect();
    }

    public function test_paying_blocking_invoice_restores_operational_access(): void
    {
        [$tenantUser, $tenant] = $this->createTenantWithUser('block_tenant_6');

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-PAY-RESTORE',
            'tenant_id' => $tenant->row_id,
            'purpose' => 'other',
            'status' => 'issued',
            'amount' => 150000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'blocks_access' => true,
        ]);

        // Initially blocked
        $this->actingAs($tenantUser)
            ->get('/dashboard')
            ->assertRedirect(route('billing.invoices.show', $invoice->row_id));

        // Pay the invoice
        $payments = app(InvoicePaymentService::class);
        $payments->recordManual($invoice, [
            'amount' => 150000,
            'paid_at' => now(),
            'notes' => 'Pembayaran lunas',
        ], $tenantUser);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);

        // Access is restored (will not redirect to billing invoice show)
        $response = $this->actingAs($tenantUser)->get('/dashboard');
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_invoice_without_blocks_access_does_not_block_operational_routes(): void
    {
        [$tenantUser, $tenant] = $this->createTenantWithUser('block_tenant_7');

        Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-NO-BLOCK',
            'tenant_id' => $tenant->row_id,
            'purpose' => 'training',
            'status' => 'issued',
            'amount' => 1000000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'blocks_access' => false,
        ]);

        $response = $this->actingAs($tenantUser)->get('/dashboard');
        $this->assertNotEquals(302, $response->getStatusCode());
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
            'name' => 'Invoice Test User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'inv_'.Str::lower(Str::random(8)),
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
