<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Services\Reports\LoanScheduleVsActualService;
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

final class LoanScheduleVsActualTest extends TestCase
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
            'name' => 'Petugas RR',
            'email' => 'rr@example.test',
            'username' => 'rr_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa RR',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-RR',
            'name' => 'Kelompok RR',
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

    public function test_schedule_vs_actual_computes_gap(): void
    {
        $this->seedJulyInstallments();

        $report = app(LoanScheduleVsActualService::class)->build(2026, 7);

        self::assertSame(1, $report['totals']['count']);
        self::assertEqualsWithDelta(100000.0, $report['totals']['plan_principal'], 0.01);
        self::assertEqualsWithDelta(40000.0, $report['totals']['actual_principal'], 0.01);
        self::assertEqualsWithDelta(60000.0, $report['totals']['gap_principal'], 0.01);
        self::assertEqualsWithDelta(15000.0, $report['totals']['plan_interest'], 0.01);
        self::assertEqualsWithDelta(0.0, $report['totals']['actual_interest'], 0.01);
        self::assertEqualsWithDelta(15000.0, $report['totals']['gap_interest'], 0.01);
    }

    public function test_schedule_vs_actual_page_renders(): void
    {
        $this->seedJulyInstallments();

        $this->actingAs($this->user)
            ->get('/lending/reports/schedule-vs-actual?year=2026&month=7')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Reports/ScheduleVsActual')
                ->where('totals.count', 1)
                ->has('rows', 1)
            );
    }

    private function seedJulyInstallments(): void
    {
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 1,
            'loan_number' => 'PK-RR-1',
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

        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'component' => 'principal',
            'installment_number' => 1,
            'due_date' => '2026-07-15',
            'principal_due' => 100000,
            'principal_paid' => 40000,
            'interest_due' => 0,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'partial',
        ]);
        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'component' => 'interest',
            'installment_number' => 1,
            'due_date' => '2026-07-15',
            'principal_due' => 0,
            'principal_paid' => 0,
            'interest_due' => 15000,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'due',
        ]);
    }
}
