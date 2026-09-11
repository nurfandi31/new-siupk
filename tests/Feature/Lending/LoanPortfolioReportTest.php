<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Services\Reports\LoanPortfolioReportService;
use App\Domain\Membership\Models\Group;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanPortfolioReportTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private int $productId;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Laporan',
            'email' => 'loan-report@example.test',
            'username' => 'loan_report_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Laporan',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-RPT',
            'name' => 'Kelompok Laporan',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_portfolio_builds_outstanding_and_overdue_aging(): void
    {
        $loan = $this->seedActiveLoanWithOverdue();

        $report = app(LoanPortfolioReportService::class)->build('2026-07-28', 'all');

        self::assertSame(1, $report['totals']['count']);
        self::assertSame(1, $report['totals']['overdue_count']);
        self::assertEqualsWithDelta(700000.0, $report['totals']['principal_remaining'], 0.01);
        self::assertEqualsWithDelta(100000.0, $report['totals']['overdue_amount'], 0.01);
        self::assertCount(1, $report['rows']);
        self::assertSame((int) $loan->id, $report['rows'][0]['id']);
        self::assertSame('Kelompok Laporan', $report['rows'][0]['group_name']);
        self::assertGreaterThan(0, $report['rows'][0]['days_overdue']);
        self::assertContains($report['rows'][0]['aging_bucket'], ['1_30', '31_60', '61_90', '90_plus']);
    }

    public function test_portfolio_filter_overdue_hides_current(): void
    {
        $this->seedActiveLoanCurrentOnly();

        $all = app(LoanPortfolioReportService::class)->build('2026-07-28', 'all');
        $overdue = app(LoanPortfolioReportService::class)->build('2026-07-28', 'overdue');
        $current = app(LoanPortfolioReportService::class)->build('2026-07-28', 'current');

        self::assertSame(1, $all['totals']['count']);
        self::assertSame(0, $overdue['totals']['count']);
        self::assertSame(1, $current['totals']['count']);
        self::assertSame(0.0, $current['totals']['overdue_amount']);
    }

    public function test_portfolio_page_renders(): void
    {
        $this->seedActiveLoanWithOverdue();

        $this->actingAs($this->user)
            ->get('/lending/reports/portfolio?as_of=2026-07-28&filter=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Reports/Portfolio')
                ->where('totals.count', 1)
                ->has('rows', 1)
                ->has('aging', 5)
            );
    }

    public function test_loan_card_pdf_streams(): void
    {
        $loan = $this->seedActiveLoanWithOverdue();

        $this->actingAs($this->user)
            ->get('/lending/loans/'.$loan->row_id.'/card')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function seedActiveLoanWithOverdue(): Loan
    {
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 1,
            'loan_number' => 'PK-RPT-1',
            'proposed_at' => '2026-01-01',
            'disbursed_at' => '2026-01-15',
            'principal_amount' => 1000000,
            'interest_rate' => 1.5,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $this->group->row_id,
            'member_row_id' => null,
        ]);

        // Overdue principal (past due)
        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'component' => 'principal',
            'installment_number' => 1,
            'due_date' => '2026-06-15',
            'principal_due' => 100000,
            'principal_paid' => 0,
            'interest_due' => 0,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'due',
        ]);
        // Future principal remaining
        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'component' => 'principal',
            'installment_number' => 2,
            'due_date' => '2026-08-15',
            'principal_due' => 600000,
            'principal_paid' => 0,
            'interest_due' => 0,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'pending',
        ]);
        // Future interest remaining
        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'component' => 'interest',
            'installment_number' => 2,
            'due_date' => '2026-08-15',
            'principal_due' => 0,
            'principal_paid' => 0,
            'interest_due' => 15000,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'pending',
        ]);

        return $loan;
    }

    private function seedActiveLoanCurrentOnly(): Loan
    {
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 2,
            'loan_number' => 'PK-RPT-2',
            'proposed_at' => '2026-01-01',
            'disbursed_at' => '2026-01-15',
            'principal_amount' => 500000,
            'interest_rate' => 1.5,
            'term_months' => 6,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $this->group->row_id,
            'member_row_id' => null,
        ]);

        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'component' => 'principal',
            'installment_number' => 1,
            'due_date' => '2026-08-20',
            'principal_due' => 500000,
            'principal_paid' => 0,
            'interest_due' => 0,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'pending',
        ]);
        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'component' => 'interest',
            'installment_number' => 1,
            'due_date' => '2026-08-20',
            'principal_due' => 0,
            'principal_paid' => 0,
            'interest_due' => 10000,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'pending',
        ]);

        return $loan;
    }
}
