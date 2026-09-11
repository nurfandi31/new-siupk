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
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantInvoicePortalTest extends TestCase
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

    public function test_tenant_user_sees_only_own_invoices(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('alpha');
        $other = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'beta',
            'name' => 'Beta Tenant',
            'status' => 'active',
        ]);

        $own = $this->makeInvoice($tenant->row_id, 'INV-OWN-1', 'issued');
        $this->makeInvoice($other->row_id, 'INV-OTHER-1', 'issued');

        $this->actingAs($user)
            ->get('/billing/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Invoices/Index')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.number', 'INV-OWN-1')
                ->where('invoices.data.0.row_id', $own->row_id));
    }

    public function test_tenant_cannot_view_foreign_invoice(): void
    {
        [$user] = $this->createTenantWithUser('alpha');
        $other = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'beta',
            'name' => 'Beta Tenant',
            'status' => 'active',
        ]);
        $foreign = $this->makeInvoice($other->row_id, 'INV-OTHER-2', 'issued');

        $this->actingAs($user)
            ->get("/billing/invoices/{$foreign->row_id}")
            ->assertNotFound();
    }

    public function test_draft_invoice_is_hidden_from_tenant(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('alpha');
        $draft = $this->makeInvoice($tenant->row_id, 'INV-DRAFT-1', 'draft');

        $this->actingAs($user)
            ->get('/billing/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Invoices/Index')
                ->has('invoices.data', 0));

        $this->actingAs($user)
            ->get("/billing/invoices/{$draft->row_id}")
            ->assertNotFound();
    }

    public function test_tenant_can_open_own_invoice_detail(): void
    {
        [$user, $tenant] = $this->createTenantWithUser('alpha');
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-OWN-2', 'issued');

        $this->actingAs($user)
            ->get("/billing/invoices/{$invoice->row_id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Invoices/Show')
                ->where('invoice.number', 'INV-OWN-2')
                ->where('invoice.is_open', true));
    }

    private function makeInvoice(int $tenantId, string $number, string $status): Invoice
    {
        return Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => $number,
            'tenant_id' => $tenantId,
            'purpose' => 'subscription',
            'status' => $status,
            'amount' => 250000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'issued_at' => $status === 'draft' ? null : now(),
            'due_at' => now()->addDays(7)->toDateString(),
            'description' => 'Langganan bulanan',
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
