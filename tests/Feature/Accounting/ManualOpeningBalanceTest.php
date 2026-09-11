<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountOpeningBalanceService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantSequenceService;
use DomainException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class ManualOpeningBalanceTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $superadmin;

    private Account $cash;

    private Account $equity;

    private Account $revenue;

    private AccountOpeningBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Super Admin',
            'email' => 'admin@example.test',
            'username' => 'admin_'.Str::lower(Str::random(6)),
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        $this->cash = Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Kas',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01',
            'name' => 'Modal',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->revenue = Account::query()->create([
            'code' => '4.1.01',
            'name' => 'Pendapatan Jasa',
            'account_type' => 'revenue',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->service = app(AccountOpeningBalanceService::class);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_saves_balanced_opening_balance_with_source_manual(): void
    {
        $written = $this->service->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 50_000_000],
        ], (int) $this->superadmin->row_id);

        self::assertSame(2, $written);

        $rows = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->get()
            ->keyBy('account_row_id');

        self::assertEquals(50_000_000.0, (float) $rows->get($this->cash->row_id)->debit);
        self::assertSame('manual', $rows->get($this->cash->row_id)->source);
        self::assertSame('manual', $rows->get($this->equity->row_id)->source);
    }

    public function test_throws_when_imbalanced(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Saldo awal tidak imbang');

        $this->service->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
            // missing equity credit line → imbalanced
        ], (int) $this->superadmin->row_id);
    }

    public function test_idempotent_when_values_unchanged(): void
    {
        $this->service->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 50_000_000],
        ], (int) $this->superadmin->row_id);

        $updatedAt1 = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->cash->row_id)
            ->value('updated_at');

        sleep(1);
        $written = $this->service->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 50_000_000],
        ], (int) $this->superadmin->row_id);

        self::assertSame(0, $written, 'Idempotent: tidak ada baris yang di-write ulang.');

        $updatedAt2 = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->cash->row_id)
            ->value('updated_at');

        self::assertSame($updatedAt1, $updatedAt2, 'updated_at tidak berubah untuk input identik.');
    }

    public function test_refuses_overwrite_migration_source(): void
    {
        // Pre-seed: saldo awal 'migration' untuk kas 2026
        $sequences = app(TenantSequenceService::class);
        $now = now();
        DB::connection('tenant')->table('account_opening_balances')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => $sequences->next('account_opening_balances'),
            'account_row_id' => $this->cash->row_id,
            'fiscal_year' => 2026,
            'debit' => 10_000_000,
            'credit' => 0,
            'source' => 'migration',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('berasal dari migrasi legacy');

        $this->service->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 50_000_000],
        ], (int) $this->superadmin->row_id);

        // Row 'migration' harus TETAP tidak ter-overwrite
        $row = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->cash->row_id)
            ->first();
        self::assertSame('migration', $row->source);
        self::assertEquals(10_000_000.0, (float) $row->debit);
    }

    public function test_update_existing_manual_source_preserves_source(): void
    {
        $this->service->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 50_000_000],
        ], (int) $this->superadmin->row_id);

        // Nilai diubah → harus update, source tetap 'manual'
        $this->service->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 75_000_000, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 75_000_000],
        ], (int) $this->superadmin->row_id);

        $row = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->cash->row_id)
            ->first();
        self::assertSame('manual', $row->source);
        self::assertEquals(75_000_000.0, (float) $row->debit);
    }

    public function test_invalid_fiscal_year_throws(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Tahun fiskal tidak valid');

        $this->service->upsert(1900, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 1, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 1],
        ], (int) $this->superadmin->row_id);
    }

    public function test_http_endpoint_requires_superadmin(): void
    {
        $nonSuperadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Tenant User',
            'email' => 'nocadmin@example.test',
            'username' => 'noc_'.Str::lower(Str::random(6)),
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        $this->actingAs($nonSuperadmin)
            ->post("/admin/tenants/{$this->testTenant->row_id}/onboarding/opening-balances/manual", [
                'fiscal_year' => 2026,
                'lines' => [],
            ])
            ->assertRedirect();
    }

    public function test_http_endpoint_persists_via_controller(): void
    {
        $this->actingAs($this->superadmin)
            ->post("/admin/tenants/{$this->testTenant->row_id}/onboarding/opening-balances/manual", [
                'fiscal_year' => 2026,
                'lines' => [
                    ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
                    ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 50_000_000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->cash->row_id)
            ->first();
        self::assertNotNull($row);
        self::assertEquals(50_000_000.0, (float) $row->debit);
        self::assertSame('manual', $row->source);
    }
}
