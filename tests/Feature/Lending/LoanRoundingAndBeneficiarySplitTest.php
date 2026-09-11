<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\LoanTrackingService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
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

final class LoanRoundingAndBeneficiarySplitTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private int $sppProductId;

    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Pinjaman',
            'email' => 'petugas_pinjaman@example.test',
            'username' => 'petugas_pinjaman',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'DS01',
            'name' => 'Desa Makmur',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-01',
            'name' => 'Kelompok Sejahtera',
            'organization_unit_row_id' => $village->row_id,
            'status' => 'active',
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->sppProductId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        // Create 3 members
        for ($i = 1; $i <= 3; $i++) {
            $person = Person::query()->create([
                'national_identity_number' => '320101010101000'.$i,
                'full_name' => "Anggota {$i}",
                'gender' => 'F',
            ]);
            $member = Member::query()->create([
                'person_row_id' => $person->row_id,
                'organization_unit_row_id' => $village->row_id,
                'member_number' => "MBR-00{$i}",
                'registered_at' => '2026-01-01',
                'status' => 'active',
            ]);
            GroupMember::query()->create([
                'group_row_id' => $this->group->row_id,
                'member_row_id' => $member->row_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
            $this->members[$i] = $member;
        }
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_installment_schedule_applies_rounding_step_and_balances_principal(): void
    {
        $service = app(LoanService::class);

        // Plafon 10.000.000, 3 bulan (10jt / 3 = 3.333.333,33)
        // Dengan pembulatan step 500:
        // Periode 1: round(3.333.333,33 / 500) * 500 = 3.333.500
        // Periode 2: round(3.333.333,33 / 500) * 500 = 3.333.500
        // Periode 3: 10.000.000 - 3.333.500 - 3.333.500 = 3.333.000
        $loan = $service->createProposal([
            'group_id' => $this->group->row_id,
            'loan_product_id' => $this->sppProductId,
            'proposed_at' => '2026-08-01',
            'principal_amount' => 10000000.0,
            'term_months' => 3,
            'service_rate_total' => 12.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'chair_id' => $this->members[1]->row_id,
            'secretary_id' => $this->members[2]->row_id,
            'treasurer_id' => $this->members[3]->row_id,
            'beneficiary_ids' => [
                $this->members[1]->row_id,
                $this->members[2]->row_id,
                $this->members[3]->row_id,
            ],
            'beneficiary_amounts' => [
                $this->members[1]->row_id => 5000000.0,
                $this->members[2]->row_id => 3000000.0,
                $this->members[3]->row_id => 2000000.0,
            ],
        ], (int) $this->user->row_id);

        $this->assertSame(500, (int) $loan->rounding_step);

        $principalInstallments = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('component', 'principal')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $principalInstallments);
        $this->assertEquals(3333500.0, (float) $principalInstallments[0]->principal_due);
        $this->assertEquals(3333500.0, (float) $principalInstallments[1]->principal_due);
        $this->assertEquals(3333000.0, (float) $principalInstallments[2]->principal_due);

        // Total principal must sum to exactly 10.000.000
        $totalScheduled = $principalInstallments->sum('principal_due');
        $this->assertEquals(10000000.0, (float) $totalScheduled);
    }

    public function test_updating_proposal_rounding_step_regenerates_schedule(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createProposal([
            'group_id' => $this->group->row_id,
            'loan_product_id' => $this->sppProductId,
            'proposed_at' => '2026-08-01',
            'principal_amount' => 10000000.0,
            'term_months' => 3,
            'service_rate_total' => 12.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'chair_id' => $this->members[1]->row_id,
            'secretary_id' => $this->members[2]->row_id,
            'treasurer_id' => $this->members[3]->row_id,
            'beneficiary_ids' => [
                $this->members[1]->row_id,
            ],
            'beneficiary_amounts' => [
                $this->members[1]->row_id => 10000000.0,
            ],
        ], (int) $this->user->row_id);

        // Update proposal with rounding_step = 5000
        // 10jt / 3 = 3.333.333,33 -> round to 5000 is 3.335.000
        // Periode 1: 3.335.000
        // Periode 2: 3.335.000
        // Periode 3: 10.000.000 - 3.335.000 - 3.335.000 = 3.330.000
        $updatedLoan = $service->updateProposal($loan, [
            'proposed_at' => '2026-08-01',
            'principal_amount' => 10000000.0,
            'service_rate_total' => 12.0,
            'term_months' => 3,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 5000,
            'beneficiary_amounts' => [
                $this->members[1]->row_id => 10000000.0,
            ],
        ], (int) $this->user->row_id);

        $this->assertSame(5000, (int) $updatedLoan->rounding_step);

        $installments = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('component', 'principal')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $installments);
        $this->assertEquals(3335000.0, (float) $installments[0]->principal_due);
        $this->assertEquals(3335000.0, (float) $installments[1]->principal_due);
        $this->assertEquals(3330000.0, (float) $installments[2]->principal_due);
        $this->assertEquals(10000000.0, (float) $installments->sum('principal_due'));
    }

    public function test_group_members_allocation_map_supports_proportional_installments(): void
    {
        $service = app(LoanService::class);
        $tracking = app(LoanTrackingService::class);

        // Group loan 50jt with non-equal allocations:
        // Member 1: 25jt (50%)
        // Member 2: 15jt (30%)
        // Member 3: 10jt (20%)
        $loan = $service->createProposal([
            'group_id' => $this->group->row_id,
            'loan_product_id' => $this->sppProductId,
            'proposed_at' => '2026-08-01',
            'principal_amount' => 50000000.0,
            'term_months' => 10,
            'service_rate_total' => 12.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->members[1]->row_id,
            'secretary_id' => $this->members[2]->row_id,
            'treasurer_id' => $this->members[3]->row_id,
            'beneficiary_ids' => [
                $this->members[1]->row_id,
                $this->members[2]->row_id,
                $this->members[3]->row_id,
            ],
            'beneficiary_amounts' => [
                $this->members[1]->row_id => 25000000.0,
                $this->members[2]->row_id => 15000000.0,
                $this->members[3]->row_id => 10000000.0,
            ],
        ], (int) $this->user->row_id);

        $groupMembers = $tracking->getGroupMembers((int) $loan->row_id);

        $this->assertCount(3, $groupMembers);
        $membersByRowId = collect($groupMembers)->keyBy('row_id');

        $this->assertEquals(25000000.0, $membersByRowId->get((int) $this->members[1]->row_id)['allocated_amount']);
        $this->assertEquals(15000000.0, $membersByRowId->get((int) $this->members[2]->row_id)['allocated_amount']);
        $this->assertEquals(10000000.0, $membersByRowId->get((int) $this->members[3]->row_id)['allocated_amount']);
    }

    public function test_sync_rounding_from_products_updates_draft_loans(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createProposal([
            'group_id' => $this->group->row_id,
            'loan_product_id' => $this->sppProductId,
            'proposed_at' => '2026-08-01',
            'principal_amount' => 10000000.0,
            'term_months' => 3,
            'service_rate_total' => 12.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'chair_id' => $this->members[1]->row_id,
            'secretary_id' => $this->members[2]->row_id,
            'treasurer_id' => $this->members[3]->row_id,
            'beneficiary_ids' => [
                $this->members[1]->row_id,
            ],
            'beneficiary_amounts' => [
                $this->members[1]->row_id => 10000000.0,
            ],
        ], (int) $this->user->row_id);

        $this->assertSame(500, (int) $loan->rounding_step);

        // Change product rounding to 5000
        DB::connection('tenant')->table('loan_products')
            ->where('row_id', $this->sppProductId)
            ->update(['rounding_method' => '5000']);

        // Sync
        $count = $service->syncRoundingFromProducts();
        $this->assertSame(1, $count);

        $loan->refresh();
        $this->assertSame(5000, (int) $loan->rounding_step);

        // Verify schedule was regenerated with new rounding
        $installments = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('component', 'principal')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $installments);
        // 10jt / 3 = 3.333.333,33 -> round to 5000 = 3.335.000
        $this->assertEquals(3335000.0, (float) $installments[0]->principal_due);
        $this->assertEquals(3335000.0, (float) $installments[1]->principal_due);
        $this->assertEquals(3330000.0, (float) $installments[2]->principal_due);
        $this->assertEquals(10000000.0, (float) $installments->sum('principal_due'));
    }
}
