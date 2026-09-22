<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Services\Reports\LppReportService;
use App\Domain\Membership\Models\Group;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LppReportScopeTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa A',
            'type' => 'village',
            'is_active' => true,
        ]);

        $group = Group::query()->create([
            'code' => 'KLP-LPP',
            'name' => 'Kelompok LPP',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        // Group loan (kelompok).
        $groupLoan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 1,
            'loan_number' => 'LN-LPP-K',
            'proposed_at' => '2026-01-01',
            'disbursed_at' => '2026-01-15',
            'principal_amount' => 5000000,
            'interest_rate' => 1.5,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);
        // sequence_number sudah auto-fill, set id manual agar deterministik.
        DB::connection('tenant')->table('loans')->where('row_id', $groupLoan->row_id)->update(['id' => 1]);
        LoanBorrower::query()->create([
            'loan_row_id' => $groupLoan->row_id,
            'group_row_id' => $group->row_id,
            'member_row_id' => null,
        ]);

        // Seed installments untuk groupLoan agar tidak di-skip.
        foreach ([1, 2] as $n) {
            LoanInstallment::query()->create([
                'loan_row_id' => $groupLoan->row_id,
                'component' => 'principal',
                'installment_number' => $n,
                'due_date' => sprintf('2026-%02d-15', $n + 5),
                'principal_due' => 250000,
                'principal_paid' => 0,
                'interest_due' => 75000,
                'interest_paid' => 0,
                'penalty_due' => 0,
                'penalty_paid' => 0,
                'status' => 'pending',
            ]);
        }

        // Individual loan.
        $personRowId = (int) DB::connection('tenant')->table('people')->insertGetId([
            'id' => 1,
            'tenant_id' => $this->testTenant->row_id,
            'public_id' => (string) Str::ulid(),
            'national_identity_number' => '3301010101900001',
            'full_name' => 'Andi Individu',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberRowId = (int) DB::connection('tenant')->table('members')->insertGetId([
            'id' => 1,
            'tenant_id' => $this->testTenant->row_id,
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $personRowId,
            'member_number' => 'AGT-001',
            'status' => 'active',
            'organization_unit_row_id' => 1,
            'registered_at' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberLoan = Loan::query()->create([
            'legacy_source' => 'member_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 2,
            'loan_number' => 'LN-LPP-I',
            'proposed_at' => '2026-01-01',
            'disbursed_at' => '2026-01-15',
            'principal_amount' => 2000000,
            'interest_rate' => 1.5,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);
        DB::connection('tenant')->table('loans')->where('row_id', $memberLoan->row_id)->update(['id' => 2]);
        LoanBorrower::query()->create([
            'loan_row_id' => $memberLoan->row_id,
            'group_row_id' => null,
            'member_row_id' => $memberRowId,
        ]);

        foreach ([1, 2] as $n) {
            LoanInstallment::query()->create([
                'loan_row_id' => $memberLoan->row_id,
                'component' => 'principal',
                'installment_number' => $n,
                'due_date' => sprintf('2026-%02d-15', $n + 5),
                'principal_due' => 100000,
                'principal_paid' => 0,
                'interest_due' => 30000,
                'interest_paid' => 0,
                'penalty_due' => 0,
                'penalty_paid' => 0,
                'status' => 'pending',
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_build_desa_scope_member_excludes_group_loans(): void
    {
        $all = app(LppReportService::class)->buildDesa(2026, 7, null, null);
        $member = app(LppReportService::class)->buildDesa(2026, 7, null, 'member');
        $group = app(LppReportService::class)->buildDesa(2026, 7, null, 'group');

        self::assertGreaterThan(0, count($all['products']), 'build_desa tanpa scope menghasilkan >=1 product block');

        // build_desa: gunakan totals.pemanfaat_count sebagai indicator jumlah loan.
        self::assertSame(2, $all['totals']['pemanfaat_count'], 'Scope null: 2 loans via totals');
        self::assertSame(1, $member['totals']['pemanfaat_count'], 'Scope member: 1 loan via totals');
        self::assertSame(1, $group['totals']['pemanfaat_count'], 'Scope group: 1 loan via totals');

        // build_desa villages berisi totals (bukan detail loans), validasi via grand totals.
        self::assertEqualsWithDelta(7000000.0, $all['totals']['alokasi'], 0.01, 'Total alokasi scope null = 7jt');
        self::assertEqualsWithDelta(2000000.0, $member['totals']['alokasi'], 0.01, 'Total alokasi scope member = 2jt');
        self::assertEqualsWithDelta(5000000.0, $group['totals']['alokasi'], 0.01, 'Total alokasi scope group = 5jt');
    }

    public function test_build_individu_excludes_group_loans(): void
    {
        $individu = app(LppReportService::class)->buildIndividu(2026, 7);

        self::assertNotEmpty($individu['products']);
        $totalLoans = $this->sumLoansAcrossProducts($individu);
        self::assertSame(1, $totalLoans, 'buildIndividu hanya mengembalikan 1 loan (individu)');

        // Verifikasi field khusus individu.
        $firstLoan = $individu['products'][0]['villages'][0]['loans'][0];
        self::assertArrayHasKey('member_name', $firstLoan);
        self::assertArrayHasKey('nik', $firstLoan);
        self::assertSame('Andi Individu', $firstLoan['member_name']);
    }

    private function sumLoansAcrossProducts(array $report): int
    {
        // Untuk build_desa: villages berisi totals (bukan detail loans). Hitung dari villages yang ada alokasi > 0.
        // Untuk buildIndividu/buildKelompok: villages berisi detail loans array.
        $count = 0;
        foreach ($report['products'] as $prod) {
            foreach ($prod['villages'] as $v) {
                if (isset($v['loans']) && is_array($v['loans'])) {
                    $count += count($v['loans']);
                } elseif (isset($v['subtotal']['peminjam_count'])) {
                    $count += (int) $v['subtotal']['peminjam_count'];
                } elseif (isset($v['subtotal']['pemanfaat_count'])) {
                    $count += (int) $v['subtotal']['pemanfaat_count'];
                } elseif (($v['alokasi'] ?? 0) > 0) {
                    // build_desa: setiap village dengan alokasi > 0 = ada minimal 1 loan.
                    $count += 1;
                }
            }
        }

        return $count;
    }
}
