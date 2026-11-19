<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Invoice;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BillingLoginGateTest extends TestCase
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

    public function test_login_redirects_tenant_user_to_blocking_invoice_page(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('login_block');
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-LOGIN-BLOCK', 'issued', true);

        $response = $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('billing.invoices.show', [
            'invoice' => $invoice->row_id,
            'locked' => 1,
        ]));
        $response->assertSessionHas('warning');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_stays_on_dashboard_when_invoice_does_not_block_access(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('login_no_block');
        $this->makeInvoice($tenant->row_id, 'INV-LOGIN-NO-BLOCK', 'issued', false);

        $response = $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_quick_login_redirects_to_blocking_invoice_page(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('login_quick_block');
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-QUICK-BLOCK', 'overdue', true);

        $response = $this->post("/login/user/{$user->row_id}");

        $response->assertRedirect(route('billing.invoices.show', [
            'invoice' => $invoice->row_id,
            'locked' => 1,
        ]));
        $response->assertSessionHas('warning');
        $this->assertAuthenticatedAs($user);
    }

    public function test_blocking_gate_is_shared_with_inertia_and_includes_invoice_details(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('login_shared_gate');
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-SHARED-GATE', 'issued', true);

        $this->actingAs($user)
            ->get("/billing/invoices/{$invoice->row_id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Invoices/Show')
                ->where('billing_gate.blocked', true)
                ->where('billing_gate.reason', 'invoice_block')
                ->where('billing_gate.invoice.row_id', $invoice->row_id)
                ->where('billing_gate.invoice.number', 'INV-SHARED-GATE'));
    }

    public function test_paid_invoice_is_not_shared_as_blocking_gate(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('login_paid_gate');
        $this->makeInvoice($tenant->row_id, 'INV-PAID-GATE', 'paid', true);

        $this->actingAs($user)
            ->get('/billing/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('billing_gate.blocked', false));
    }

    public function test_tenant_user_cannot_toggle_blocking_flag(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('tenant_cannot_toggle');
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-TENANT-CANNOT-TOGGLE', 'issued', false);

        $this->actingAs($user)
            ->post("/admin/invoices/{$invoice->row_id}/toggle-blocking")
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertFalse((bool) $invoice->fresh()->blocks_access);
    }

    public function test_tenant_user_cannot_create_invoice_from_central_panel(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('tenant_cannot_create');

        $this->actingAs($user)
            ->post('/admin/invoices', [
                'tenant_id' => $tenant->row_id,
                'purpose' => 'other',
                'amount' => 100000,
                'currency' => 'IDR',
                'description' => 'Percobaan dari tenant',
                'status' => 'issued',
                'blocks_access' => true,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::query()->where('tenant_id', $tenant->row_id)->count());
    }

    public function test_central_toggle_on_immediately_blocks_tenant_login(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('central_toggle_on');
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-CENTRAL-ON', 'issued', false);

        $superadmin = $this->createSuperadmin();

        // Pusat mengaktifkan blokir.
        $this->actingAs($superadmin)
            ->post("/admin/invoices/{$invoice->row_id}/toggle-blocking")
            ->assertRedirect();
        $this->assertTrue((bool) $invoice->fresh()->blocks_access);

        Auth::logout();

        // Tenant langsung diarahkan ke halaman tagihan saat login.
        $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('billing.invoices.show', [
            'invoice' => $invoice->row_id,
            'locked' => 1,
        ]));
    }

    public function test_central_toggle_off_immediately_restores_tenant_login(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('central_toggle_off');
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-CENTRAL-OFF', 'issued', true);

        $superadmin = $this->createSuperadmin();

        // Pusat mematikan blokir.
        $this->actingAs($superadmin)
            ->post("/admin/invoices/{$invoice->row_id}/toggle-blocking")
            ->assertRedirect();
        $this->assertFalse((bool) $invoice->fresh()->blocks_access);

        Auth::logout();

        // Login tenant kembali normal ke dashboard.
        $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    private function createSuperadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Central Superadmin',
            'email' => 'central-'.Str::lower(Str::random(6)).'@example.test',
            'username' => 'central_'.Str::lower(Str::random(8)),
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);
    }

    private function makeInvoice(int $tenantId, string $number, string $status, bool $blocksAccess): Invoice
    {
        return Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => $number,
            'tenant_id' => $tenantId,
            'purpose' => 'support',
            'status' => $status,
            'amount' => 500000,
            'amount_paid' => $status === 'paid' ? 500000 : 100000,
            'currency' => 'IDR',
            'due_at' => now()->addDays(3)->toDateString(),
            'description' => 'Biaya Layanan Tahunan',
            'blocks_access' => $blocksAccess,
        ]);
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
            'name' => 'Billing Gate User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'gate_'.Str::lower(Str::random(8)),
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
