<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
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

final class EntityDetailTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Member $member;

    private Group $group;

    private Loan $loan;

    private OrganizationUnit $institution;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Detail',
            'email' => 'detail@example.test',
            'username' => 'detail_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Detail',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->institution = OrganizationUnit::query()->create([
            'id' => 2,
            'code' => 'INS-1',
            'name' => 'Lembaga Mitra',
            'type' => 'other_institution',
            'parent_row_id' => 1,
            'institution_identity_number' => 'INS-001',
            'leader_name' => 'Budi',
            'is_active' => true,
        ]);

        $person = Person::query()->create([
            'full_name' => 'Siti Anggota',
            'national_identity_number' => '3273010203040099',
            'gender' => 'P',
            'phone' => '08123456789',
        ]);

        $this->member = Member::query()->create([
            'person_row_id' => $person->row_id,
            'member_number' => 'M-DET-1',
            'organization_unit_row_id' => 1,
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-DET',
            'name' => 'Kelompok Detail',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        GroupMember::query()->create([
            'group_row_id' => $this->group->row_id,
            'member_row_id' => $this->member->row_id,
            'joined_at' => '2026-01-01',
            'status' => 'active',
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $this->loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 1,
            'loan_number' => 'PK-DET-1',
            'proposed_at' => '2026-02-01',
            'disbursed_at' => '2026-02-15',
            'principal_amount' => 3000000,
            'interest_rate' => 1.5,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $this->loan->row_id,
            'group_row_id' => $this->group->row_id,
            'member_row_id' => null,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $this->loan->row_id,
            'member_row_id' => $this->member->row_id,
            'allocated_amount' => 1000000,
        ]);

        LoanInstallment::query()->create([
            'loan_row_id' => $this->loan->row_id,
            'component' => 'principal',
            'installment_number' => 1,
            'due_date' => '2026-03-15',
            'principal_due' => 3000000,
            'principal_paid' => 500000,
            'interest_due' => 0,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'partial',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_member_show_includes_loan_history(): void
    {
        $this->actingAs($this->user)
            ->get('/master-data/members/'.$this->member->row_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Members/Show')
                ->where('member.name', 'Siti Anggota')
                ->where('summary.loan_count', 1)
                ->has('loans', 1)
                ->where('loans.0.id', $this->loan->id)
                ->where('loans.0.href', '/lending/loans/'.$this->loan->row_id)
            );
    }

    public function test_group_show_includes_loan_history(): void
    {
        $this->actingAs($this->user)
            ->get('/master-data/groups/'.$this->group->row_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Groups/Show')
                ->where('group.name', 'Kelompok Detail')
                ->where('summary.loan_count', 1)
                ->has('loans', 1)
                ->where('loans.0.row_id', $this->loan->row_id)
            );
    }

    public function test_institution_show_renders_empty_loan_history(): void
    {
        $this->actingAs($this->user)
            ->get('/master-data/institutions/'.$this->institution->row_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Institutions/Show')
                ->where('institution.name', 'Lembaga Mitra')
                ->where('summary.loan_count', 0)
                ->has('loans', 0)
            );
    }
}
