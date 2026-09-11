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

final class LoanProposalDeletionTest extends TestCase
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

    public function test_draft_proposal_can_be_deleted(): void
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
            'chair_id' => $this->members[1]->row_id,
            'secretary_id' => $this->members[2]->row_id,
            'treasurer_id' => $this->members[3]->row_id,
            'beneficiary_ids' => [
                $this->members[1]->row_id,
                $this->members[2]->row_id,
            ],
            'beneficiary_amounts' => [
                $this->members[1]->row_id => 6000000.0,
                $this->members[2]->row_id => 4000000.0,
            ],
        ], (int) $this->user->row_id);

        $loanRowId = (int) $loan->row_id;
        $this->assertSame('draft', $loan->status);

        $response = $this->actingAs($this->user)->delete("/lending/loans/{$loanRowId}");

        $response->assertRedirect('/lending/loans');
        $this->assertNull(Loan::query()->find($loanRowId));
        $this->assertSame(0, LoanBeneficiary::query()->where('loan_row_id', $loanRowId)->count());
        $this->assertSame(0, LoanBorrower::query()->where('loan_row_id', $loanRowId)->count());
        $this->assertSame(0, LoanCommittee::query()->where('loan_row_id', $loanRowId)->count());
        $this->assertSame(0, LoanInstallment::query()->where('loan_row_id', $loanRowId)->count());
    }

    public function test_non_draft_loan_cannot_be_deleted(): void
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

        // Advance to verified
        $loan->forceFill(['status' => 'verified'])->save();

        $response = $this->actingAs($this->user)->delete("/lending/loans/{$loan->row_id}");

        $response->assertSessionHas('error');
        $this->assertNotNull(Loan::query()->find($loan->row_id));
    }
}
