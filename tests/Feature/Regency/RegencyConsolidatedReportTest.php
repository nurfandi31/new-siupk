<?php

declare(strict_types=1);

namespace Tests\Feature\Regency;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\Reports\RegencyConsolidatedReportService;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegencyConsolidatedReportTest extends TestCase
{
    private DatabaseShard $shard;

    private Tenant $kecamatan1;

    private Tenant $kecamatan2;

    private User $regencySupervisor;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        if (! filter_var(env('RUN_TENANCY_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_TENANCY_INTEGRATION_TESTS=true to run MySQL tenancy tests.');
        }

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);

        $this->setupMultiKecamatanData();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_regular_tenant_user_cannot_access_regency_routes(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get('/regency/dashboard');

        // Regency middleware redirects unauthorized users
        $response->assertRedirect();
    }

    public function test_regency_supervisor_can_access_dashboard_and_reports(): void
    {
        $this->actingAs($this->regencySupervisor)
            ->get('/regency/dashboard')
            ->assertOk();

        $this->actingAs($this->regencySupervisor)
            ->get('/regency/reports/balance-sheet?year=2026&month=7')
            ->assertOk();

        $this->actingAs($this->regencySupervisor)
            ->get('/regency/reports/income-statement?year=2026&month=7')
            ->assertOk();

        $this->actingAs($this->regencySupervisor)
            ->get('/regency/reports/general-ledger?year=2026&month=7')
            ->assertOk();

        $this->actingAs($this->regencySupervisor)
            ->get('/regency/reports/cash-flow?year=2026&month=7')
            ->assertOk();

        $this->actingAs($this->regencySupervisor)
            ->get('/regency/reports/calk?year=2026&month=7')
            ->assertOk();
    }

    public function test_consolidated_balance_sheet_aggregates_both_kecamatans(): void
    {
        $service = app(RegencyConsolidatedReportService::class);
        $tenantIds = [(int) $this->kecamatan1->row_id, (int) $this->kecamatan2->row_id];

        // Consolidated
        $report = $service->balanceSheet($this->shard, $tenantIds, 2026, 7);

        // Tenant 1: cash 1.400.000 (modal 1.000.000 + laba 400.000)
        // Tenant 2: cash 2.500.000 (modal 2.000.000 + laba 500.000)
        // Consolidated Total Assets = 3.900.000
        self::assertTrue($report['is_consolidated']);
        self::assertEqualsWithDelta(3900000.0, $report['summary']['total_assets'], 0.02);
        self::assertEqualsWithDelta(3900000.0, $report['summary']['total_equity'], 0.02);
        self::assertTrue($report['summary']['is_balanced']);

        // Specific Kecamatan 1 filter
        $reportKec1 = $service->balanceSheet($this->shard, $tenantIds, 2026, 7, (int) $this->kecamatan1->row_id);
        self::assertFalse($reportKec1['is_consolidated']);
        self::assertEqualsWithDelta(1400000.0, $reportKec1['summary']['total_assets'], 0.02);
        self::assertEqualsWithDelta(1400000.0, $reportKec1['summary']['total_equity'], 0.02);
    }

    public function test_consolidated_income_statement_aggregates_revenue_and_expenses(): void
    {
        $service = app(RegencyConsolidatedReportService::class);
        $tenantIds = [(int) $this->kecamatan1->row_id, (int) $this->kecamatan2->row_id];

        // Consolidated
        $report = $service->incomeStatement($this->shard, $tenantIds, 2026, 7);

        // Tenant 1: revenue 500.000, expense 100.000 -> net 400.000
        // Tenant 2: revenue 800.000, expense 300.000 -> net 500.000
        // Combined revenue = 1.300.000, expense = 400.000 -> net = 900.000
        self::assertEqualsWithDelta(1300000.0, $report['summary']['revenue_ops']['ytd'], 0.02);
        self::assertEqualsWithDelta(400000.0, $report['summary']['expense_ops']['ytd'], 0.02);
        self::assertEqualsWithDelta(900000.0, $report['summary']['after_tax']['ytd'], 0.02);

        // Single Kecamatan 2 filter
        $reportKec2 = $service->incomeStatement($this->shard, $tenantIds, 2026, 7, (int) $this->kecamatan2->row_id);
        self::assertEqualsWithDelta(800000.0, $reportKec2['summary']['revenue_ops']['ytd'], 0.02);
        self::assertEqualsWithDelta(300000.0, $reportKec2['summary']['expense_ops']['ytd'], 0.02);
        self::assertEqualsWithDelta(500000.0, $reportKec2['summary']['after_tax']['ytd'], 0.02);
    }

    public function test_consolidated_cash_flow_and_calk(): void
    {
        $service = app(RegencyConsolidatedReportService::class);
        $tenantIds = [(int) $this->kecamatan1->row_id, (int) $this->kecamatan2->row_id];

        // Cash Flow
        $cf = $service->cashFlow($this->shard, $tenantIds, 2026, 7);
        self::assertEqualsWithDelta(3900000.0, $cf['reconciliation']['cash_closing'], 0.02);
        self::assertEqualsWithDelta(3900000.0, $cf['reconciliation']['net_change'], 0.02);

        // CALK
        $calk = $service->calk($this->shard, $tenantIds, 2026, 7);
        self::assertNotEmpty($calk['highlights']);
        self::assertCount(2, $calk['kecamatans']);
    }

    public function test_general_ledger_entries_from_multiple_kecamatans(): void
    {
        $service = app(RegencyConsolidatedReportService::class);
        $tenantIds = [(int) $this->kecamatan1->row_id, (int) $this->kecamatan2->row_id];

        $gl = $service->generalLedger($this->shard, $tenantIds, 2026, 7, '1.1.01.01');
        self::assertEqualsWithDelta(3900000.0, $gl['closing_balance'], 0.02);
        self::assertCount(6, $gl['entries']); // 3 entries from Kec 1 + 3 entries from Kec 2
    }

    public function test_regency_pdf_generation_streams_successfully(): void
    {
        $types = ['balance-sheet', 'income-statement', 'general-ledger', 'cash-flow', 'calk'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->regencySupervisor)
                ->get("/regency/reports/{$type}/pdf?year=2026&month=7");

            $response->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        }
    }

    private function setupMultiKecamatanData(): void
    {
        // 1. Shard Kabupaten Cilacap
        $this->shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'shard-kab-cilacap',
            'name' => 'Shard Kabupaten Cilacap',
            'regency_code' => '33.01',
            'regency_name' => 'Cilacap',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => 3306,
            'database_name' => (string) config('database.connections.tenant.database'),
            'credential_reference' => 'test',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        // 2. Kecamatan 1 (Cilacap Selatan)
        $this->kecamatan1 = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'kec-cilacap-selatan',
            'name' => 'Kecamatan Cilacap Selatan',
            'regency_code' => '33.01',
            'regency_name' => 'Cilacap',
            'district_code' => '330101',
            'map_latitude' => -7.585,
            'map_longitude' => 108.798,
            'map_zoom' => 13,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $placement1 = TenantPlacement::query()->create([
            'tenant_id' => $this->kecamatan1->row_id,
            'shard_id' => $this->shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $this->kecamatan1->row_id,
            'public_id' => $this->kecamatan1->public_id,
            'code' => $this->kecamatan1->code,
            'name' => $this->kecamatan1->name,
            'district_code' => '330101',
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Kecamatan 2 (Cilacap Tengah)
        $this->kecamatan2 = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'kec-cilacap-tengah',
            'name' => 'Kecamatan Cilacap Tengah',
            'regency_code' => '33.01',
            'regency_name' => 'Cilacap',
            'district_code' => '330102',
            'map_latitude' => -7.616,
            'map_longitude' => 109.112,
            'map_zoom' => 13,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $placement2 = TenantPlacement::query()->create([
            'tenant_id' => $this->kecamatan2->row_id,
            'shard_id' => $this->shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $this->kecamatan2->row_id,
            'public_id' => $this->kecamatan2->public_id,
            'code' => $this->kecamatan2->code,
            'name' => $this->kecamatan2->name,
            'district_code' => '330102',
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Users
        $this->regencySupervisor = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Supervisor Bapermasdes Cilacap',
            'email' => 'supervisor.cilacap@example.test',
            'username' => 'spv_cilacap',
            'password' => 'password',
            'status' => 'active',
            'is_regency_user' => true,
            'regency_code' => '33.01',
            'regency_name' => 'Cilacap',
        ]);

        $this->regularUser = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Staff Kec Selatan',
            'email' => 'staff.selatan@example.test',
            'username' => 'staff_selatan',
            'password' => 'password',
            'status' => 'active',
            'tenant_id' => $this->kecamatan1->row_id,
            'is_regency_user' => false,
        ]);

        // 5. Seed Journals for Kecamatan 1
        $this->seedTenantAccountingData($this->kecamatan1, $placement1, 1000000.0, 500000.0, 100000.0);

        // 6. Seed Journals for Kecamatan 2
        $this->seedTenantAccountingData($this->kecamatan2, $placement2, 2000000.0, 800000.0, 300000.0);
    }

    private function seedTenantAccountingData(Tenant $tenant, TenantPlacement $placement, float $capital, float $revenue, float $expense): void
    {
        app(TenantContext::class)->initialize($tenant, $placement, $this->shard);

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        $l1Asset = Account::query()->create([
            'code' => '1.0.00.00', 'name' => 'Aktiva', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 1, 'is_postable' => false, 'is_active' => true,
        ]);
        $l2Asset = Account::query()->create([
            'code' => '1.1.00.00', 'name' => 'Aktiva Lancar', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Asset->row_id,
        ]);
        $l3Cash = Account::query()->create([
            'code' => '1.1.01.00', 'name' => 'Kas dan Bank', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 3, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l2Asset->row_id,
        ]);
        $cashAcc = Account::query()->create([
            'code' => '1.1.01.01', 'name' => 'Kas Operasional', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l3Cash->row_id,
        ]);

        $l1Equity = Account::query()->create([
            'code' => '3.0.00.00', 'name' => 'Ekuitas', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 1, 'is_postable' => false, 'is_active' => true,
        ]);
        $l2Equity = Account::query()->create([
            'code' => '3.1.00.00', 'name' => 'Ekuitas Terikat', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Equity->row_id,
        ]);
        $l3Equity = Account::query()->create([
            'code' => '3.1.01.00', 'name' => 'Modal', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 3, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l2Equity->row_id,
        ]);
        $equityAcc = Account::query()->create([
            'code' => '3.1.01.01', 'name' => 'Modal Pemilik', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l3Equity->row_id,
        ]);

        $l2Earn = Account::query()->create([
            'code' => '3.2.00.00', 'name' => 'Laba Rugi', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Equity->row_id,
        ]);
        $l3Earn = Account::query()->create([
            'code' => '3.2.02.00', 'name' => 'Laba Berjalan', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 3, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l2Earn->row_id,
        ]);
        Account::query()->create([
            'code' => '3.2.02.01', 'name' => 'Laba/Rugi Tahun Berjalan', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l3Earn->row_id,
        ]);

        $l1Rev = Account::query()->create([
            'code' => '4.0.00.00', 'name' => 'Pendapatan', 'account_type' => 'revenue',
            'normal_balance' => 'C', 'level' => 1, 'is_postable' => false, 'is_active' => true,
        ]);
        $l2Rev = Account::query()->create([
            'code' => '4.1.00.00', 'name' => 'Pendapatan Usaha', 'account_type' => 'revenue',
            'normal_balance' => 'C', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Rev->row_id,
        ]);
        $revAcc = Account::query()->create([
            'code' => '4.1.01.01', 'name' => 'Pendapatan Jasa', 'account_type' => 'revenue',
            'normal_balance' => 'C', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l2Rev->row_id,
        ]);

        $l1Exp = Account::query()->create([
            'code' => '5.0.00.00', 'name' => 'Beban', 'account_type' => 'expense',
            'normal_balance' => 'D', 'level' => 1, 'is_postable' => false, 'is_active' => true,
        ]);
        $l2Exp = Account::query()->create([
            'code' => '5.1.00.00', 'name' => 'Beban Usaha', 'account_type' => 'expense',
            'normal_balance' => 'D', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Exp->row_id,
        ]);
        $expAcc = Account::query()->create([
            'code' => '5.1.01.01', 'name' => 'Beban Operasional', 'account_type' => 'expense',
            'normal_balance' => 'D', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l2Exp->row_id,
        ]);

        $poster = app(JournalPostingService::class);

        // 1. Setor Modal
        $e1 = JournalEntry::query()->create([
            'transaction_date' => '2026-07-05',
            'sequence_number' => 1,
            'description' => 'Setor modal awal',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e1->row_id, 'line_number' => 1,
            'account_row_id' => $cashAcc->row_id, 'debit' => number_format($capital, 2, '.', ''), 'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e1->row_id, 'line_number' => 2,
            'account_row_id' => $equityAcc->row_id, 'debit' => '0.00', 'credit' => number_format($capital, 2, '.', ''),
        ]);
        $poster->post($e1, 1);

        // 2. Pendapatan
        $e2 = JournalEntry::query()->create([
            'transaction_date' => '2026-07-10',
            'sequence_number' => 2,
            'description' => 'Pendapatan jasa pinjaman',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e2->row_id, 'line_number' => 1,
            'account_row_id' => $cashAcc->row_id, 'debit' => number_format($revenue, 2, '.', ''), 'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e2->row_id, 'line_number' => 2,
            'account_row_id' => $revAcc->row_id, 'debit' => '0.00', 'credit' => number_format($revenue, 2, '.', ''),
        ]);
        $poster->post($e2, 1);

        // 3. Beban
        $e3 = JournalEntry::query()->create([
            'transaction_date' => '2026-07-15',
            'sequence_number' => 3,
            'description' => 'Beban operasional kantor',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e3->row_id, 'line_number' => 1,
            'account_row_id' => $expAcc->row_id, 'debit' => number_format($expense, 2, '.', ''), 'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e3->row_id, 'line_number' => 2,
            'account_row_id' => $cashAcc->row_id, 'debit' => '0.00', 'credit' => number_format($expense, 2, '.', ''),
        ]);
        $poster->post($e3, 1);
    }
}
