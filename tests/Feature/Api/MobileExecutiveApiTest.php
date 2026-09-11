<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
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

final class MobileExecutiveApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $director;

    private Loan $verifiedLoan;

    private Member $member;

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

        $this->director = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Direktur Operasional',
            'username' => 'direktur_app',
            'email' => 'direktur.app@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'user_id' => $this->director->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->token = $this->director->createToken('Flutter Test')->plainTextToken;

        // Create Village
        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'DS001',
            'name' => 'Desa Makmur Jaya',
            'type' => 'village',
        ]);

        // Create Group
        $this->group = Group::query()->create([
            'id' => 1,
            'code' => 'GRP-001',
            'name' => 'Kelompok Mawar Indah',
            'organization_unit_row_id' => $village->row_id,
            'address' => 'Jl. Desa Makmur RT 01',
            'status' => 'active',
        ]);

        // Create Member
        $person = Person::query()->create([
            'id' => 1,
            'full_name' => 'Dewi Lestari',
            'national_identity_number' => '3201018801000001',
            'phone' => '081311223344',
        ]);

        $this->member = Member::query()->create([
            'id' => 1,
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'MBR-001',
            'registered_at' => CarbonImmutable::now()->toDateString(),
            'status' => 'active',
        ]);

        $product = LoanProduct::query()->where('code', 'spp')->first()
            ?? LoanProduct::query()->first();

        // Create Verified Loan
        $this->verifiedLoan = Loan::query()->create([
            'id' => 1,
            'legacy_source' => 'group_loan',
            'loan_number' => 'PROP-2026-0099',
            'loan_product_row_id' => $product->row_id,
            'sequence_number' => 1,
            'proposed_at' => CarbonImmutable::now()->subDays(5)->toDateString(),
            'verified_at' => CarbonImmutable::now()->subDays(1)->toDateString(),
            'principal_amount' => 15000000.00,
            'interest_rate' => 0.015,
            'service_rate_total' => 0.18,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'verified',
            'verification_notes' => 'Hasil verifikasi lapangan: usaha konveksi sangat produktif.',
            'created_by_user_id' => $this->director->row_id,
        ]);

        LoanBorrower::query()->create([
            'id' => 1,
            'loan_row_id' => $this->verifiedLoan->row_id,
            'group_row_id' => $this->group->row_id,
        ]);

        LoanBeneficiary::query()->create([
            'id' => 1,
            'loan_row_id' => $this->verifiedLoan->row_id,
            'member_row_id' => $this->member->row_id,
            'proposed_amount' => 15000000.00,
            'allocated_amount' => 15000000.00,
            'verified_amount' => 15000000.00,
        ]);
    }

    public function test_director_can_get_executive_summary(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->getJson('/api/v1/mobile/executive/summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'as_of_date',
                    'cash_balance',
                    'bank_balance',
                    'total_liquidity',
                    'active_loans_count',
                    'outstanding_principal',
                    'pending_verification_count',
                    'pending_approval_count',
                    'today_collections_amount',
                    'today_collections_count',
                    'this_month_disbursed_amount',
                ],
            ]);
    }

    public function test_director_can_list_approval_queue(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->getJson('/api/v1/mobile/executive/approvals');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.loan_number', 'PROP-2026-0099')
            ->assertJsonPath('data.0.borrower_name', 'Kelompok Mawar Indah');
    }

    public function test_director_can_get_approval_detail(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->getJson("/api/v1/mobile/executive/approvals/{$this->verifiedLoan->row_id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loan_number', 'PROP-2026-0099')
            ->assertJsonPath('data.borrower_name', 'Kelompok Mawar Indah')
            ->assertJsonPath('data.beneficiaries.0.verified_amount', 15000000);
    }

    public function test_director_can_approve_loan_proposal(): void
    {
        $today = CarbonImmutable::now()->toDateString();
        $disburseDate = CarbonImmutable::now()->addDays(7)->toDateString();

        $payload = [
            'approved_at' => $today,
            'planned_disbursed_at' => $disburseDate,
            'allocated_principal' => 15000000,
            'allocation_notes' => 'Disetujui penuh sesuai rekomendasi verifikasi.',
            'beneficiaries' => [
                [
                    'member_row_id' => $this->member->row_id,
                    'allocated_amount' => 15000000,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->postJson("/api/v1/mobile/executive/approvals/{$this->verifiedLoan->row_id}/approve", $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.allocated_principal', 15000000);

        $this->assertDatabaseHas('loans', [
            'row_id' => $this->verifiedLoan->row_id,
            'status' => 'waiting',
        ], 'tenant');
    }

    public function test_director_can_reject_loan_proposal(): void
    {
        $payload = [
            'reason' => 'Data jaminan kurang lengkap dan perlu konfirmasi ulang.',
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Tenant-Code' => $this->testTenant->code,
        ])->postJson("/api/v1/mobile/executive/approvals/{$this->verifiedLoan->row_id}/reject", $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('loans', [
            'row_id' => $this->verifiedLoan->row_id,
            'status' => 'draft',
        ], 'tenant');
    }
}
