<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\Reports\BalanceSheetService;
use App\Domain\Accounting\Services\Reports\CalkService;
use App\Domain\Accounting\Services\Reports\CashFlowService;
use App\Domain\Accounting\Services\Reports\EquityChangeService;
use App\Domain\Accounting\Services\Reports\GeneralLedgerService;
use App\Domain\Accounting\Services\Reports\IncomeStatementService;
use App\Domain\Accounting\Services\Reports\JournalListingService;
use App\Domain\Accounting\Services\Reports\TrialBalanceService;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class AccountingReportsTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private Account $cash;

    private Account $equity;

    private Account $revenue;

    private Account $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->seedCoaAndPeriod();
        $this->postSampleJournals();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_trial_balance_is_balanced(): void
    {
        $report = app(TrialBalanceService::class)->build(2026, 7);

        self::assertTrue($report['balanced'], 'Trial balance should balance');
        self::assertNotEmpty($report['rows']);
        self::assertEqualsWithDelta($report['totals']['ns_debit'], $report['totals']['ns_credit'], 0.02);
    }

    public function test_balance_sheet_reflects_current_earnings(): void
    {
        $report = app(BalanceSheetService::class)->build(2026, 7);

        self::assertEqualsWithDelta(400000.0, $report['totals']['net_income'], 0.02);
        self::assertTrue($report['balanced'], 'Balance sheet should balance');
    }

    public function test_income_statement_net_income(): void
    {
        $report = app(IncomeStatementService::class)->build(2026, 7);

        self::assertEqualsWithDelta(400000.0, $report['summary']['before_tax']['ytd'], 0.02);
        self::assertEqualsWithDelta(400000.0, $report['summary']['after_tax']['ytd'], 0.02);
    }

    public function test_general_ledger_cash_closing(): void
    {
        $report = app(GeneralLedgerService::class)->build(2026, 7, (int) $this->cash->row_id);

        self::assertEqualsWithDelta(1400000.0, $report['totals']['closing_balance'], 0.02);
        self::assertNotEmpty($report['rows']);
    }

    public function test_journal_listing_totals_balance(): void
    {
        $report = app(JournalListingService::class)->build(2026, 7, null, 1, 100);

        self::assertTrue($report['balanced']);
        self::assertEqualsWithDelta($report['totals']['debit'], $report['totals']['credit'], 0.02);
        self::assertGreaterThan(0, $report['pagination']['total']);
    }

    public function test_cash_flow_reconciles_opening_plus_net_to_closing(): void
    {
        $report = app(CashFlowService::class)->build(2026, 7);

        self::assertTrue($report['reconciled'], 'Opening + net should match closing cash');
        // Sample journals: +1.000.000 modal (financing) +400.000 revenue net of expense via cash
        // postSample: cash +1.000.000 then +500.000 revenue -100.000 expense = closing 1.400.000
        self::assertEqualsWithDelta(1400000.0, $report['closing_cash'], 0.02);
        self::assertEqualsWithDelta($report['opening_cash'] + $report['net_change'], $report['closing_cash'], 0.05);
        self::assertNotEmpty($report['sections']);
    }

    public function test_equity_change_closing_includes_capital_and_earnings(): void
    {
        $report = app(EquityChangeService::class)->build(2026, 7);

        // Modal 1.000.000 + laba 400.000
        self::assertEqualsWithDelta(1400000.0, $report['summary']['closing_total'], 0.02);
        self::assertEqualsWithDelta(400000.0, $report['summary']['period_net_income'], 0.02);
        self::assertNotEmpty($report['bridge']);
        self::assertNotEmpty($report['rows']);
    }

    public function test_calk_builds_highlights_and_saves_notes(): void
    {
        $report = app(CalkService::class)->build(2026, 7);

        self::assertNotEmpty($report['highlights']);
        self::assertNotEmpty($report['policies']);
        self::assertEqualsWithDelta(400000.0, $report['highlights'][0]['amount'], 0.02);

        app(CalkService::class)->saveNotes('Catatan uji CALK');
        $again = app(CalkService::class)->build(2026, 7);
        self::assertSame('Catatan uji CALK', $again['notes']);
    }

    private function seedCoaAndPeriod(): void
    {
        $accountCreatedAt = CarbonImmutable::parse('2026-07-01')->startOfDay();

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        $l1Asset = Account::query()->create([
            'code' => '1.0.00.00', 'name' => 'Aset', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 1, 'is_postable' => false, 'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $l2Asset = Account::query()->create([
            'code' => '1.1.00.00', 'name' => 'Aset Lancar', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Asset->row_id,
            'created_at' => $accountCreatedAt,
        ]);
        $l3Asset = Account::query()->create([
            'code' => '1.1.01.00', 'name' => 'Kas', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 3, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l2Asset->row_id,
            'created_at' => $accountCreatedAt,
        ]);
        $this->cash = Account::query()->create([
            'code' => '1.1.01.01', 'name' => 'Kas Tunai', 'account_type' => 'asset',
            'normal_balance' => 'D', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l3Asset->row_id,
            'created_at' => $accountCreatedAt,
        ]);

        $l1Equity = Account::query()->create([
            'code' => '3.0.00.00', 'name' => 'Modal', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 1, 'is_postable' => false, 'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $l2Equity = Account::query()->create([
            'code' => '3.1.00.00', 'name' => 'Modal Disetor', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Equity->row_id,
            'created_at' => $accountCreatedAt,
            'created_at' => $accountCreatedAt,
        ]);
        $l3Equity = Account::query()->create([
            'code' => '3.1.01.00', 'name' => 'Modal', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 3, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l2Equity->row_id,
            'created_at' => $accountCreatedAt,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01.01', 'name' => 'Modal Pemilik', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l3Equity->row_id,
            'created_at' => $accountCreatedAt,
        ]);

        $l2Earn = Account::query()->create([
            'code' => '3.2.00.00', 'name' => 'Laba Rugi', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Equity->row_id,
            'created_at' => $accountCreatedAt,
        ]);
        $l3Earn = Account::query()->create([
            'code' => '3.2.02.00', 'name' => 'Laba Berjalan', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 3, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l2Earn->row_id,
            'created_at' => $accountCreatedAt,
        ]);
        Account::query()->create([
            'code' => '3.2.02.01', 'name' => 'Laba/Rugi Tahun Berjalan', 'account_type' => 'equity',
            'normal_balance' => 'C', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l3Earn->row_id,
            'created_at' => $accountCreatedAt,
        ]);

        $l1Rev = Account::query()->create([
            'code' => '4.0.00.00', 'name' => 'Pendapatan', 'account_type' => 'revenue',
            'normal_balance' => 'C', 'level' => 1, 'is_postable' => false, 'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $l2Rev = Account::query()->create([
            'code' => '4.1.00.00', 'name' => 'Pendapatan Usaha', 'account_type' => 'revenue',
            'normal_balance' => 'C', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Rev->row_id,
            'created_at' => $accountCreatedAt,
        ]);
        $this->revenue = Account::query()->create([
            'code' => '4.1.01.01', 'name' => 'Pendapatan Jasa', 'account_type' => 'revenue',
            'normal_balance' => 'C', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l2Rev->row_id,
            'created_at' => $accountCreatedAt,
        ]);

        $l1Exp = Account::query()->create([
            'code' => '5.0.00.00', 'name' => 'Beban', 'account_type' => 'expense',
            'normal_balance' => 'D', 'level' => 1, 'is_postable' => false, 'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $l2Exp = Account::query()->create([
            'code' => '5.1.00.00', 'name' => 'Beban Usaha', 'account_type' => 'expense',
            'normal_balance' => 'D', 'level' => 2, 'is_postable' => false, 'is_active' => true,
            'parent_row_id' => $l1Exp->row_id,
            'created_at' => $accountCreatedAt,
        ]);
        $this->expense = Account::query()->create([
            'code' => '5.1.01.01', 'name' => 'Beban Operasional', 'account_type' => 'expense',
            'normal_balance' => 'D', 'level' => 4, 'is_postable' => true, 'is_active' => true,
            'parent_row_id' => $l2Exp->row_id,
            'created_at' => $accountCreatedAt,
        ]);
    }

    private function postSampleJournals(): void
    {
        $poster = app(JournalPostingService::class);

        $e1 = JournalEntry::query()->create([
            'transaction_date' => '2026-07-05',
            'sequence_number' => 1,
            'description' => 'Setor modal',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e1->row_id, 'line_number' => 1,
            'account_row_id' => $this->cash->row_id, 'debit' => '1000000.00', 'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e1->row_id, 'line_number' => 2,
            'account_row_id' => $this->equity->row_id, 'debit' => '0.00', 'credit' => '1000000.00',
        ]);
        $poster->post($e1, 1);

        $e2 = JournalEntry::query()->create([
            'transaction_date' => '2026-07-10',
            'sequence_number' => 2,
            'description' => 'Pendapatan jasa',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e2->row_id, 'line_number' => 1,
            'account_row_id' => $this->cash->row_id, 'debit' => '500000.00', 'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e2->row_id, 'line_number' => 2,
            'account_row_id' => $this->revenue->row_id, 'debit' => '0.00', 'credit' => '500000.00',
        ]);
        $poster->post($e2, 1);

        $e3 = JournalEntry::query()->create([
            'transaction_date' => '2026-07-15',
            'sequence_number' => 3,
            'description' => 'Beban operasional',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e3->row_id, 'line_number' => 1,
            'account_row_id' => $this->expense->row_id, 'debit' => '100000.00', 'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $e3->row_id, 'line_number' => 2,
            'account_row_id' => $this->cash->row_id, 'debit' => '0.00', 'credit' => '100000.00',
        ]);
        $poster->post($e3, 1);
    }
}
