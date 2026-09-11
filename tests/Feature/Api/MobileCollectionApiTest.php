<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
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

final class MobileCollectionApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $collector;

    private Loan $loan;

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

        $this->collector = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Kolektor Lapangan',
            'username' => 'kolektor_app',
            'email' => 'kolektor.app@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'user_id' => $this->collector->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->token = $this->collector->createToken('Flutter Test')->plainTextToken;

        // Create Village
        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'type' => 'village',
            'code' => '33.01.01.2001',
            'name' => 'Desa Sukamaju',
            'is_active' => true,
        ]);

        // Create Group
        $this->group = Group::query()->create([
            'id' => 1,
            'organization_unit_row_id' => $village->row_id,
            'code' => 'GRP-001',
            'name' => 'Kelompok Mawar',
            'address' => 'RT 01 RW 02',
            'status' => 'active',
        ]);

        // Create Person & Member
        $person = Person::query()->create([
            'id' => 1,
            'full_name' => 'Siti Aminah',
            'national_identity_number' => '3301015001900001',
            'phone' => '081234567890',
            'gender' => 'F',
        ]);

        $this->member = Member::query()->create([
            'id' => 1,
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'MBR-001',
            'status' => 'active',
            'registered_at' => now()->toDateString(),
        ]);

        // Product
        $product = LoanProduct::query()->where('code', 'spp')->first();

        // Create Active Loan
        $this->loan = Loan::query()->create([
            'id' => 1,
            'legacy_source' => 'group_loan',
            'public_id' => (string) Str::ulid(),
            'loan_product_row_id' => $product->row_id,
            'loan_number' => 'SPP-2026-001',
            'status' => 'active',
            'principal_amount' => 10000000.00,
            'interest_rate' => 0.0120,
            'term_months' => 10,
            'installment_method' => 'monthly',
            'disbursed_at' => CarbonImmutable::now()->subMonth()->toDateString(),
        ]);

        // Link Borrower
        LoanBorrower::query()->create([
            'id' => 1,
            'loan_row_id' => $this->loan->row_id,
            'group_row_id' => $this->group->row_id,
        ]);

        // Link Beneficiary
        LoanBeneficiary::query()->create([
            'id' => 1,
            'loan_row_id' => $this->loan->row_id,
            'member_row_id' => $this->member->row_id,
            'allocated_amount' => 10000000.00,
        ]);

        // Installment schedule (10 months)
        for ($i = 1; $i <= 10; $i++) {
            LoanInstallment::query()->create([
                'id' => $i,
                'loan_row_id' => $this->loan->row_id,
                'installment_number' => $i,
                'due_date' => CarbonImmutable::now()->addMonths($i - 1)->toDateString(),
                'principal_due' => 1000000.00,
                'interest_due' => 120000.00,
                'principal_paid' => 0.00,
                'interest_paid' => 0.00,
                'status' => 'pending',
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_collector_can_search_and_list_active_loans(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/mobile/collection/loans?search=Mawar');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.borrower_name', 'Kelompok Mawar')
            ->assertJsonPath('data.items.0.village_name', 'Desa Sukamaju');

        $this->assertEquals(10000000, $response->json('data.items.0.principal_amount'));
        $this->assertEquals(10000000, $response->json('data.items.0.remaining_principal'));
    }

    public function test_collector_can_get_loan_collection_detail(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/mobile/collection/loans/{$this->loan->row_id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.borrower_name', 'Kelompok Mawar')
            ->assertJsonPath('data.beneficiaries.0.name', 'Siti Aminah');

        $this->assertEquals(10000000, $response->json('data.remaining_principal'));
        $this->assertEquals(1200000, $response->json('data.remaining_interest'));
        $this->assertEquals(1000000, $response->json('data.suggested_principal'));
        $this->assertEquals(120000, $response->json('data.suggested_interest'));
    }

    public function test_collector_can_record_installment_payment_and_receive_receipt(): void
    {
        $cashAccount = Account::on('tenant')->where('is_postable', true)->where('code', 'like', '1.1.01.%')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/mobile/collection/loans/{$this->loan->row_id}/pay", [
                'member_id' => $this->member->row_id,
                'principal_amount' => 1000000,
                'interest_amount' => 120000,
                'penalty_amount' => 0,
                'cash_account_row_id' => $cashAccount?->row_id,
                'description' => 'Setoran angsuran ke-1 di balai desa',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Pembayaran angsuran berhasil dicatat.')
            ->assertJsonPath('data.payer_name', 'Siti Aminah');

        $this->assertEquals(1120000, $response->json('data.total_paid'));
        $this->assertEquals(9000000, $response->json('data.remaining_principal'));
        $this->assertEquals(1080000, $response->json('data.remaining_interest'));
    }
}
