<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Invoice;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuditLogTest extends TestCase
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

        DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'host' => 'mysql',
            'database_name' => 'siupk_shard_test',
            'credential_reference' => 'test',
            'status' => 'active',
        ]);
    }

    public function test_superadmin_can_open_audit_log_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/audit-logs')
            ->assertOk();
    }

    public function test_non_superadmin_cannot_access_audit_logs(): void
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
            ->get('/admin/audit-logs')
            ->assertRedirect(route('login'));
    }

    public function test_tenant_suspend_is_audited(): void
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'T-'.Str::upper(Str::random(6)),
            'name' => 'BUMDesma Audit',
            'status' => 'active',
            'provisioned_at' => now(),
        ]);

        $this->actingAs($this->superadmin())
            ->post("/admin/tenants/{$tenant->row_id}/suspend")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.suspend',
            'tenant_id' => $tenant->row_id,
        ], 'platform');
    }

    public function test_invoice_void_is_audited(): void
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'T-'.Str::upper(Str::random(6)),
            'name' => 'BUMDesma Invoice',
            'status' => 'active',
            'provisioned_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-'.Str::upper(Str::random(8)),
            'tenant_id' => $tenant->row_id,
            'status' => 'issued',
            'purpose' => 'subscription',
            'amount' => 500000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);

        $this->actingAs($this->superadmin())
            ->post("/admin/invoices/{$invoice->row_id}/void")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invoice.void',
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->row_id,
        ], 'platform');
    }

    public function test_audit_logger_never_throws_on_failure(): void
    {
        // Simulasi tabel tidak ada: logger harus di-report, bukan melempar exception.
        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
        Schema::connection('platform')->drop('audit_logs');

        app(AuditLogger::class)->record('system.smoke_test', description: 'should not throw');

        $this->assertTrue(true);
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
