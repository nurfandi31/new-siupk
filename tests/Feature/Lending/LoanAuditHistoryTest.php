<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanStatusHistory;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanAuditHistoryTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private array $committee = [];

    private array $beneficiaries = [];

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Audit',
            'email' => 'audit@example.test',
            'username' => 'audit_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create(['id' => 1, 'code' => 'V001', 'name' => 'Desa Induk', 'type' => 'village', 'is_active' => true]);
        $this->group = Group::query()->create([
            'code' => 'KLP-AUDIT',
            'name' => 'Kelompok Audit',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $this->committee = [
            $this->createMember('Budi Ketua', '3273010203040101', 'AUD-KET'),
            $this->createMember('Siti Sekretaris', '3273010203040102', 'AUD-SEK'),
            $this->createMember('Andi Bendahara', '3273010203040103', 'AUD-BEN'),
        ];
        $this->beneficiaries = [
            $this->createMember('Dewi Pemanfaat', '3273010203040104', 'AUD-ANG1'),
            $this->createMember('Eka Pemanfaat', '3273010203040105', 'AUD-ANG2'),
        ];

        foreach ([
            ['chair', $this->committee[0]],
            ['secretary', $this->committee[1]],
            ['treasurer', $this->committee[2]],
        ] as [$position, $member]) {
            GroupOfficer::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $member->row_id, 'position' => $position, 'started_at' => '2026-01-01']);
        }

        foreach ([...$this->committee, ...$this->beneficiaries] as $member) {
            GroupMember::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $member->row_id, 'joined_at' => '2026-01-01', 'status' => 'active']);
        }

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Kas Audit',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_lifecycle_snapshots_store_complete_parameters_and_allocation_changes(): void
    {
        $loan = app(LoanService::class)->createProposal([
            'loan_product_id' => $this->productId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-09-01',
            'principal_amount' => 10_000_000,
            'service_rate_total' => 9.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->committee[0]->row_id,
            'secretary_id' => $this->committee[1]->row_id,
            'treasurer_id' => $this->committee[2]->row_id,
            'beneficiary_ids' => array_map(fn (Member $member) => $member->row_id, $this->beneficiaries),
        ], (int) $this->user->row_id);

        $proposal = $this->history($loan, 'draft');
        $this->assertSnapshot($proposal, 10_000_000, 9.0, 12, 'monthly', 'monthly', 0, 0);

        app(LoanService::class)->verify($loan, [
            'verified_at' => '2026-09-02',
            'verification_amount' => 10_000_000,
            'verification_notes' => 'Hasil survei disetujui.',
            'term_months' => 10,
            'service_rate_total' => 8.0,
            'principal_frequency' => 'bimonthly',
            'interest_frequency' => 'quarterly',
            'principal_grace_months' => 2,
            'interest_grace_months' => 3,
        ], (int) $this->user->row_id);

        $verification = $this->history($loan, 'verified');
        $this->assertSnapshot($verification, 10_000_000, 8.0, 10, 'bimonthly', 'quarterly', 2, 3);

        $beneficiaries = $loan->beneficiaries->map(fn ($beneficiary): array => [
            'member_row_id' => $beneficiary->member_row_id,
            'allocated_amount' => 4_000_000,
        ])->all();

        app(LoanService::class)->approve($loan, [
            'approved_at' => '2026-09-03',
            'planned_disbursed_at' => '2026-09-05',
            'allocated_principal' => 8_000_000,
            'allocation_notes' => 'Forum menetapkan alokasi lebih kecil.',
            'beneficiaries' => $beneficiaries,
            'term_months' => 9,
            'service_rate_total' => 7.5,
            'principal_frequency' => 'quarterly',
            'interest_frequency' => 'every_4_months',
            'principal_grace_months' => 3,
            'interest_grace_months' => 4,
        ], (int) $this->user->row_id);

        $allocation = $this->history($loan, 'waiting');
        $this->assertSnapshot($allocation, 8_000_000, 7.5, 9, 'quarterly', 'every_4_months', 3, 4);

        $this->assertSnapshot($this->history($loan, 'draft'), 10_000_000, 9.0, 12, 'monthly', 'monthly', 0, 0);
        $this->assertSnapshot($this->history($loan, 'verified'), 10_000_000, 8.0, 10, 'bimonthly', 'quarterly', 2, 3);
    }

    public function test_show_includes_audit_snapshot_and_user_name(): void
    {
        $loan = app(LoanService::class)->createProposal([
            'loan_product_id' => $this->productId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-09-01',
            'principal_amount' => 10_000_000,
            'service_rate_total' => 9.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->committee[0]->row_id,
            'secretary_id' => $this->committee[1]->row_id,
            'treasurer_id' => $this->committee[2]->row_id,
            'beneficiary_ids' => array_map(fn (Member $member) => $member->row_id, $this->beneficiaries),
        ], (int) $this->user->row_id);

        $this->actingAs($this->user)
            ->get('/lending/loans/'.$loan->row_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Show')
                ->has('loan.status_histories', 1)
                ->where('loan.status_histories.0.changed_by_user_name', 'Petugas Audit')
                ->where('loan.status_histories.0.service_rate_total', 9)
                ->where('loan.status_histories.0.principal_frequency', 'monthly'));
    }

    private function history(Loan $loan, string $status): LoanStatusHistory
    {
        $history = LoanStatusHistory::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('to_status', $status)
            ->orderBy('changed_at')
            ->first();

        self::assertNotNull($history, "Missing {$status} history.");

        return $history;
    }

    private function assertSnapshot(
        LoanStatusHistory $history,
        float $principalAmount,
        float $serviceRateTotal,
        int $termMonths,
        string $principalFrequency,
        string $interestFrequency,
        int $principalGraceMonths,
        int $interestGraceMonths,
    ): void {
        self::assertSame($principalAmount, (float) $history->principal_amount);
        self::assertSame($serviceRateTotal, (float) $history->service_rate_total);
        self::assertSame($termMonths, (int) $history->term_months);
        self::assertSame($principalFrequency, (string) $history->principal_frequency);
        self::assertSame($interestFrequency, (string) $history->interest_frequency);
        self::assertSame($principalGraceMonths, (int) $history->principal_grace_months);
        self::assertSame($interestGraceMonths, (int) $history->interest_grace_months);
    }

    private function createMember(string $name, string $nik, string $memberNumber): Member
    {
        $person = Person::query()->create([
            'national_identity_number' => $nik,
            'full_name' => $name,
            'gender' => 'L',
        ]);

        return Member::query()->create([
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => 1,
            'member_number' => $memberNumber,
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
    }
}
