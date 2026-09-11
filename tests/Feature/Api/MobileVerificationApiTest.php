<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanCommittee;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Platform\TenantMembership;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\FiscalPeriodProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MobileVerificationApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $surveyor;

    private Loan $loan;

    private Member $member1;

    private Member $member2;

    private Group $group;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        app(FiscalPeriodProvisioner::class)->ensureDefaults();

        $this->surveyor = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Petugas Survei',
            'username' => 'surveyor_app',
            'email' => 'surveyor.app@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'user_id' => $this->surveyor->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->token = $this->surveyor->createToken('Flutter Test')->plainTextToken;

        // Create Village
        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'DS001',
            'name' => 'Desa Sukamaju',
            'type' => 'village',
        ]);

        // Create Group
        $this->group = Group::query()->create([
            'id' => 1,
            'code' => 'GRP-001',
            'name' => 'Kelompok Tani Makmur',
            'organization_unit_row_id' => $village->row_id,
            'address' => 'Dusun 1 RT 02 RW 01',
            'status' => 'active',
            'established_at' => CarbonImmutable::now()->subYears(2)->toDateString(),
        ]);

        // Create Member 1
        $person1 = Person::query()->create([
            'id' => 1,
            'full_name' => 'Siti Aminah',
            'national_identity_number' => '3201019001000001',
            'phone' => '081234567890',
        ]);

        $this->member1 = Member::query()->create([
            'id' => 1,
            'person_row_id' => $person1->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'MBR-001',
            'registered_at' => CarbonImmutable::now()->subYears(2)->toDateString(),
            'status' => 'active',
        ]);

        // Create Member 2
        $person2 = Person::query()->create([
            'id' => 2,
            'full_name' => 'Budi Santoso',
            'national_identity_number' => '3201019001000002',
            'phone' => '081298765432',
        ]);

        $this->member2 = Member::query()->create([
            'id' => 2,
            'person_row_id' => $person2->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'MBR-002',
            'registered_at' => CarbonImmutable::now()->subYears(2)->toDateString(),
            'status' => 'active',
        ]);

        $product = LoanProduct::query()->where('code', 'spp')->first()
            ?? LoanProduct::query()->first();

        // Create Draft Loan Proposal
        $this->loan = Loan::query()->create([
            'id' => 1,
            'legacy_source' => 'group_loan',
            'loan_number' => 'PROP-2026-0001',
            'loan_product_row_id' => $product->row_id,
            'sequence_number' => 1,
            'proposed_at' => CarbonImmutable::now()->subDays(3)->toDateString(),
            'principal_amount' => 10000000.00,
            'interest_rate' => 0.015,
            'service_rate_total' => 0.18,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'draft',
            'created_by_user_id' => $this->surveyor->row_id,
        ]);

        LoanBorrower::query()->create([
            'id' => 1,
            'loan_row_id' => $this->loan->row_id,
            'group_row_id' => $this->group->row_id,
        ]);

        LoanCommittee::query()->create([
            'id' => 1,
            'loan_row_id' => $this->loan->row_id,
            'position' => 'chair',
            'member_row_id' => $this->member1->row_id,
            'member_name_snapshot' => 'Siti Aminah',
            'snapshot_at' => CarbonImmutable::now()->toDateString(),
        ]);

        LoanBeneficiary::query()->create([
            'id' => 1,
            'loan_row_id' => $this->loan->row_id,
            'member_row_id' => $this->member1->row_id,
            'proposed_amount' => 5000000.00,
            'allocated_amount' => 5000000.00,
            'verified_amount' => null,
        ]);

        LoanBeneficiary::query()->create([
            'id' => 2,
            'loan_row_id' => $this->loan->row_id,
            'member_row_id' => $this->member2->row_id,
            'proposed_amount' => 5000000.00,
            'allocated_amount' => 5000000.00,
            'verified_amount' => null,
        ]);
    }

    public function test_surveyor_can_list_proposals(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->getJson('/api/v1/mobile/verification/proposals');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.loan_number', 'PROP-2026-0001')
            ->assertJsonPath('data.0.borrower_name', 'Kelompok Tani Makmur')
            ->assertJsonPath('data.0.proposed_amount', 10000000)
            ->assertJsonPath('data.0.beneficiary_count', 2);
    }

    public function test_surveyor_can_get_proposal_detail_and_5c_structure(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->getJson("/api/v1/mobile/verification/proposals/{$this->loan->row_id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loan_number', 'PROP-2026-0001')
            ->assertJsonPath('data.group_name', 'Kelompok Tani Makmur')
            ->assertJsonPath('data.beneficiaries.0.full_name', 'Siti Aminah')
            ->assertJsonPath('data.beneficiaries.0.proposed_amount', 5000000)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'row_id',
                    'loan_number',
                    'status',
                    'product',
                    'borrower_name',
                    'committee',
                    'beneficiaries',
                    'evaluation_5c_guide',
                ],
            ]);
    }

    public function test_surveyor_can_submit_verification_and_survey(): void
    {
        $today = CarbonImmutable::now()->toDateString();

        $payload = [
            'verified_at' => $today,
            'verification_notes' => 'Usaha warung dan ternak aktif, sangat layak didanai.',
            'verified_amounts' => [
                $this->member1->row_id => 5000000,
                $this->member2->row_id => 4500000,
            ],
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'scoring_5c' => [
                'character' => 5,
                'capacity' => 4,
                'capital' => 4,
                'collateral' => 5,
                'condition' => 4,
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->postJson("/api/v1/mobile/verification/proposals/{$this->loan->row_id}/verify", $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.verified_amount', 9500000);

        $this->assertDatabaseHas('loans', [
            'row_id' => $this->loan->row_id,
            'status' => 'verified',
        ], 'tenant');
    }

    public function test_verification_validates_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->postJson("/api/v1/mobile/verification/proposals/{$this->loan->row_id}/verify", []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['verified_at']);
    }
}
