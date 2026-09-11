<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Invoice;
use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\TenantResolver;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
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

    public function test_non_superadmin_cannot_access_admin(): void
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'User',
            'username' => 'tenantuser',
            'email' => 'user@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('login'));
    }

    public function test_superadmin_can_open_admin_dashboard(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin')
            ->assertOk();
    }

    public function test_superadmin_can_open_revenue_monitor(): void
    {
        $admin = $this->superadmin();
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant1',
            'name' => 'BUMDesma Maju Bersama',
            'status' => 'active',
        ]);

        $plan = Plan::query()->create([
            'code' => 'pro',
            'name' => 'Pro Monthly',
            'price_amount' => 250000,
            'currency' => 'IDR',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $sub = Subscription::query()->create([
            'tenant_id' => $tenant->row_id,
            'plan_id' => $plan->row_id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-202608-0001',
            'tenant_id' => $tenant->row_id,
            'subscription_id' => $sub->row_id,
            'status' => 'issued',
            'amount' => 250000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'issued_at' => now(),
            'due_at' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/revenue')
            ->assertOk();
    }

    public function test_manual_payment_marks_invoice_paid(): void
    {
        $admin = $this->superadmin();
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'demo',
            'name' => 'Demo Tenant',
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-202607-00001',
            'tenant_id' => $tenant->row_id,
            'status' => 'issued',
            'amount' => 100000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'issued_at' => now(),
            'due_at' => now()->addDays(7)->toDateString(),
            'description' => 'Test',
            'created_by' => $admin->row_id,
        ]);

        $this->actingAs($admin)
            ->post("/admin/invoices/{$invoice->row_id}/payments/manual", [
                'amount' => 100000,
                'reference' => 'TRF-1',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('100000.00', (string) $invoice->amount_paid);
    }

    public function test_plan_can_be_created(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post('/admin/plans', [
                'code' => 'basic',
                'name' => 'Basic',
                'price_amount' => 150000,
                'currency' => 'IDR',
                'billing_period' => 'monthly',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.plans.index'));

        $this->assertTrue(Plan::query()->where('code', 'basic')->exists());
    }

    public function test_superadmin_can_set_and_resolve_custom_domains(): void
    {
        $admin = $this->superadmin();
        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'shard1',
            'name' => 'Shard 1',
            'driver' => 'sqlite',
            'host' => 'localhost',
            'port' => 3306,
            'database_name' => ':memory:',
            'credential_reference' => 'local',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'sukamaju',
            'name' => 'BUMDesma Suka Maju',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        // Update with custom domain via Admin controller
        $this->actingAs($admin)
            ->put("/admin/tenants/{$tenant->row_id}", [
                'name' => 'BUMDesma Suka Maju Updated',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'custom_domains' => ['bumdesma-sukamaju.id', 'https://app.sukamaju.desa.id/'],
            ])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertSame(['bumdesma-sukamaju.id', 'app.sukamaju.desa.id'], $tenant->metadata['domains']);

        // Verify resolver resolves the host correctly
        $resolver = app(TenantResolver::class);
        $resolved = $resolver->resolveByHost('bumdesma-sukamaju.id');
        $this->assertSame($tenant->row_id, $resolved->row_id);

        $resolvedSub = $resolver->resolveByHost('app.sukamaju.desa.id');
        $this->assertSame($tenant->row_id, $resolvedSub->row_id);
    }

    public function test_duplicate_custom_domain_across_tenants_is_rejected(): void
    {
        $admin = $this->superadmin();

        $tenantA = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'status' => 'active',
            'metadata' => ['domains' => ['app.custom-domain.id']],
        ]);

        $tenantB = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put("/admin/tenants/{$tenantB->row_id}", [
                'name' => 'Tenant B',
                'status' => 'active',
                'custom_domains' => ['app.custom-domain.id'],
            ])
            ->assertSessionHasErrors('custom_domains');
    }

    public function test_tripay_callback_rejects_bad_signature(): void
    {
        config([
            'tripay.private_key' => 'test-private',
            'tripay.merchant_code' => 'T',
            'tripay.api_key' => 'K',
        ]);

        $this->call(
            'POST',
            '/tripay/callback',
            ['merchant_ref' => 'x', 'status' => 'PAID', 'reference' => 'R1'],
            [],
            [],
            ['HTTP_X_CALLBACK_SIGNATURE' => 'invalid', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['merchant_ref' => 'x', 'status' => 'PAID', 'reference' => 'R1']),
        )->assertStatus(403);
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin_'.Str::lower(Str::random(6)),
            'email' => 'superadmin_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }
}
