<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanCommittee;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanProposalRegistrationTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private Member $chair;

    private Member $secretary;

    private Member $treasurer;

    private array $beneficiaries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas',
            'email' => 'petugas@example.test',
            'username' => 'petugas',
            'password' => 'password',
            'status' => 'active',
        ]);

        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Induk',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-001',
            'name' => 'Kelompok Tani Maju',
            'status' => 'active',
            'organization_unit_row_id' => $village->row_id,
        ]);

        $this->chair = $this->createMember('Budi Ketua', '3273010203040001', 'KET-001');
        $this->secretary = $this->createMember('Siti Sekretaris', '3273010203040002', 'SEK-001');
        $this->treasurer = $this->createMember('Andi Bendahara', '3273010203040003', 'BEN-001');
        $this->beneficiaries = [
            $this->createMember('Dewi Pemanfaat', '3273010203040004', 'ANG-001'),
            $this->createMember('Eka Pemanfaat', '3273010203040005', 'ANG-002'),
            $this->createMember('Fajar Pemanfaat', '3273010203040006', 'ANG-003'),
        ];

        GroupOfficer::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $this->chair->row_id, 'position' => 'chair', 'started_at' => '2026-01-01']);
        GroupOfficer::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $this->secretary->row_id, 'position' => 'secretary', 'started_at' => '2026-01-01']);
        GroupOfficer::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $this->treasurer->row_id, 'position' => 'treasurer', 'started_at' => '2026-01-01']);

        foreach ([$this->chair, $this->secretary, $this->treasurer, ...$this->beneficiaries] as $member) {
            GroupMember::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $member->row_id, 'joined_at' => '2026-01-01', 'status' => 'active']);
        }

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_store_creates_loan_proposal_with_committee_snapshot_and_beneficiaries(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $payload = [
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6000000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => array_map(fn (Member $m) => $m->row_id, $this->beneficiaries),
        ];

        $response = $this->actingAs($this->user)
            ->post('/lending/loans', $payload);

        $loan = Loan::query()->firstOrFail();
        $response->assertRedirect("/lending/loans/{$loan->row_id}");

        self::assertSame(1, Loan::query()->count());
        self::assertSame(1, LoanBorrower::query()->count());
        self::assertSame(3, LoanCommittee::query()->count());
        self::assertSame(3, LoanBeneficiary::query()->count());
        self::assertSame(12, LoanInstallment::query()->count());

        $loan = Loan::query()->with(['borrower', 'committee', 'beneficiaries', 'installments'])->first();
        self::assertSame('draft', $loan->status);
        self::assertSame('group_loan', $loan->legacy_source);
        self::assertSame('monthly', $loan->principal_frequency);
        self::assertSame('monthly', $loan->interest_frequency);
        self::assertSame(9.0, (float) $loan->service_rate_total);
        self::assertSame(1.5, (float) $loan->interest_rate);
        self::assertSame($this->group->row_id, $loan->borrower->group_row_id);
        self::assertNull($loan->borrower->member_row_id);

        $positions = $loan->committee->pluck('position')->all();
        sort($positions);
        self::assertSame(['chair', 'secretary', 'treasurer'], $positions);
        self::assertSame('Budi Ketua', $loan->committee->firstWhere('position', 'chair')->member_name_snapshot);

        $allocated = $loan->beneficiaries->pluck('allocated_amount');
        self::assertSame(2000000.00, (float) $allocated[0]);

        $principalRows = $loan->installments->where('component', 'principal')->values();
        $interestRows = $loan->installments->where('component', 'interest')->values();
        self::assertCount(6, $principalRows);
        self::assertCount(6, $interestRows);
        self::assertSame(1000000.00, (float) $principalRows[0]->principal_due);
        self::assertSame(0.0, (float) $principalRows[0]->interest_due);
        self::assertSame(90000.00, (float) $interestRows[0]->interest_due);
        self::assertSame(0.0, (float) $interestRows[0]->principal_due);
    }

    public function test_store_supports_per_beneficiary_amounts_when_provided(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $payload = [
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6000000,
            'service_rate_total' => 9,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => array_map(fn (Member $m) => $m->row_id, $this->beneficiaries),
            'beneficiary_amounts' => [
                $this->beneficiaries[0]->row_id => 2500000,
                $this->beneficiaries[1]->row_id => 2000000,
                $this->beneficiaries[2]->row_id => 1500000,
            ],
        ];

        $this->actingAs($this->user)->post('/lending/loans', $payload)->assertRedirect();

        $loan = Loan::query()->firstOrFail();
        $byMember = $loan->beneficiaries->keyBy('member_row_id');
        self::assertSame(2500000.0, (float) $byMember[$this->beneficiaries[0]->row_id]->allocated_amount);
        self::assertSame(2000000.0, (float) $byMember[$this->beneficiaries[1]->row_id]->allocated_amount);
        self::assertSame(1500000.0, (float) $byMember[$this->beneficiaries[2]->row_id]->allocated_amount);
    }

    public function test_store_rejects_per_beneficiary_amounts_exceeding_principal(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $payload = [
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6000000,
            'service_rate_total' => 9,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => array_map(fn (Member $m) => $m->row_id, $this->beneficiaries),
            'beneficiary_amounts' => [
                $this->beneficiaries[0]->row_id => 5000000,
                $this->beneficiaries[1]->row_id => 5000000,
                $this->beneficiaries[2]->row_id => 5000000,
            ],
        ];

        $this->actingAs($this->user)
            ->post('/lending/loans', $payload)
            ->assertSessionHasErrors();
    }

    public function test_separate_frequencies_for_principal_and_interest(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $loan = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6000000,
            'service_rate_total' => 6.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'quarterly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => array_map(fn (Member $m) => $m->row_id, $this->beneficiaries),
        ], (int) $this->user->row_id);

        self::assertSame('monthly', $loan->principal_frequency);
        self::assertSame('quarterly', $loan->interest_frequency);

        $principalRows = $loan->installments->where('component', 'principal');
        $interestRows = $loan->installments->where('component', 'interest');
        self::assertCount(6, $principalRows);
        self::assertCount(2, $interestRows);

        $principalAmounts = $principalRows->pluck('principal_due')->map(fn ($v) => (float) $v)->all();
        self::assertSame(1000000.00, $principalAmounts[0]);

        $interestAmounts = $interestRows->pluck('interest_due')->map(fn ($v) => (float) $v)->all();
        self::assertSame(180000.00, $interestAmounts[0]);
        self::assertSame(180000.00, $interestAmounts[1]);
    }

    public function test_annuity_method_produces_decreasing_interest_in_interest_schedule(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $loan = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 1200000,
            'service_rate_total' => 3.0,
            'term_months' => 3,
            'installment_method' => 'annuity',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => array_map(fn (Member $m) => $m->row_id, $this->beneficiaries),
        ], (int) $this->user->row_id);

        $interestRows = $loan->installments->where('component', 'interest')->values();
        self::assertCount(3, $interestRows);
        self::assertGreaterThan((float) $interestRows[1]->interest_due, (float) $interestRows[0]->interest_due);
    }

    public function test_store_rejects_duplicate_committee_positions(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $this->actingAs($this->user)
            ->post('/lending/loans', [
                'loan_product_id' => $sppId,
                'group_id' => $this->group->row_id,
                'proposed_at' => '2026-07-20',
                'principal_amount' => 1000000,
                'service_rate_total' => 9.0,
                'term_months' => 6,
                'installment_method' => 'flat',
                'principal_frequency' => 'monthly',
                'interest_frequency' => 'monthly',
                'chair_id' => $this->chair->row_id,
                'secretary_id' => $this->chair->row_id,
                'treasurer_id' => $this->treasurer->row_id,
                'beneficiary_ids' => [$this->beneficiaries[0]->row_id],
            ])
            ->assertSessionHasErrors('secretary_id');

        self::assertSame(0, Loan::query()->count());
    }

    public function test_store_rejects_when_beneficiaries_empty(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $this->actingAs($this->user)
            ->post('/lending/loans', [
                'loan_product_id' => $sppId,
                'group_id' => $this->group->row_id,
                'proposed_at' => '2026-07-20',
                'principal_amount' => 1000000,
                'service_rate_total' => 9.0,
                'term_months' => 6,
                'installment_method' => 'flat',
                'principal_frequency' => 'monthly',
                'interest_frequency' => 'monthly',
                'chair_id' => $this->chair->row_id,
                'secretary_id' => $this->secretary->row_id,
                'treasurer_id' => $this->treasurer->row_id,
                'beneficiary_ids' => [],
            ])
            ->assertSessionHasErrors('beneficiary_ids');

        self::assertSame(0, Loan::query()->count());
    }

    public function test_index_lists_loans_filtered_by_tabs(): void
    {
        $sppId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        // 1. Proposal (status draft)
        $loan1 = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 1000000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => [$this->beneficiaries[0]->row_id],
        ], (int) $this->user->row_id);

        // 2. Verifikasi (status verified)
        $loan2 = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 2000000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => [$this->beneficiaries[0]->row_id],
        ], (int) $this->user->row_id);
        $loan2->update(['status' => 'verified']);

        // 3. Waiting (status waiting)
        $loan3 = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 3000000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => [$this->beneficiaries[0]->row_id],
        ], (int) $this->user->row_id);
        $loan3->update(['status' => 'waiting']);

        // 4. Aktif (status active / disbursed, installments principal_due > principal_paid)
        $loan4 = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 4000000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => [$this->beneficiaries[0]->row_id],
        ], (int) $this->user->row_id);
        $loan4->update(['status' => 'active']);

        // 5. Lunas (status active / disbursed / completed, installments principal_due = principal_paid)
        $loan5 = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 5000000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => [$this->beneficiaries[0]->row_id],
        ], (int) $this->user->row_id);
        $loan5->update(['status' => 'active']);
        DB::connection('tenant')->table('loan_installments')
            ->where('loan_row_id', $loan5->row_id)
            ->update([
                'principal_paid' => DB::raw('principal_due'),
                'interest_paid' => DB::raw('interest_due'),
            ]);

        // Test Proposal Tab
        $this->actingAs($this->user)
            ->get('/lending/loans?tab=proposal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Index')
                ->where('tab', 'proposal')
                ->has('loans.data', 1)
                ->where('loans.data.0.row_id', $loan1->row_id)
            );

        // Test Verifikasi Tab
        $this->actingAs($this->user)
            ->get('/lending/loans?tab=verifikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Index')
                ->where('tab', 'verifikasi')
                ->has('loans.data', 1)
                ->where('loans.data.0.row_id', $loan2->row_id)
            );

        // Test Waiting Tab
        $this->actingAs($this->user)
            ->get('/lending/loans?tab=waiting')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Index')
                ->where('tab', 'waiting')
                ->has('loans.data', 1)
                ->where('loans.data.0.row_id', $loan3->row_id)
            );

        // Test Aktif Tab
        $this->actingAs($this->user)
            ->get('/lending/loans?tab=aktif')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Index')
                ->where('tab', 'aktif')
                ->has('loans.data', 1)
                ->where('loans.data.0.row_id', $loan4->row_id)
            );

        // Test Lunas Tab
        $this->actingAs($this->user)
            ->get('/lending/loans?tab=lunas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Index')
                ->where('tab', 'lunas')
                ->has('loans.data', 1)
                ->where('loans.data.0.row_id', $loan5->row_id)
            );
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
            'organization_unit_row_id' => OrganizationUnit::query()->where('type', 'village')->first()->row_id,
            'member_number' => $memberNumber,
            'registered_at' => '2026-01-01',
            'status' => 'active',
            'registered_by_user_id' => $this->user->row_id,
        ]);
    }
}
