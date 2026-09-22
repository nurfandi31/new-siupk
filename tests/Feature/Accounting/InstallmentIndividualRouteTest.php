<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
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

/**
 * Paritas dengan legacy SIUPK `TransaksiController::jurnalAngsuran` &
 * `jurnalAngsuranIndividu` — route pairs:
 * - /accounting/journal-entries/installment              (loan kelompok, legacy_source != member_loan)
 * - /accounting/journal-entries/installment-individual  (loan individu, legacy_source = 'member_loan')
 */
final class InstallmentIndividualRouteTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private int $productId;

    private int $groupProductId;

    private static int $loanIdCounter = 0;

    private static int $villageIdCounter = 0;

    private static int $borrowerIdCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::$loanIdCounter = 0;
        self::$villageIdCounter = 0;
        self::$borrowerIdCounter = 0;
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Pinjaman Individu',
            'email' => 'kasir-individu@example.test',
            'username' => 'kasir_individu',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Maju Bersama',
            'short_name' => 'KMB',
            'address' => 'Jl. Raya No. 1',
            'phone' => '08123456789',
            'email' => 'info@kmb.test',
            'installment_rounding' => '5000',
            'disbursement_cutoff_day' => 25,
            'village_installment_day' => 15,
        ]);

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(TenantLoanProductProvisioner::class)->ensureDefaults();

        $this->productId = (int) DB::connection('tenant')
            ->table('loan_products')
            ->where('code', 'pi')
            ->value('row_id');

        $this->groupProductId = (int) DB::connection('tenant')
            ->table('loan_products')
            ->where('code', 'spp')
            ->value('row_id');

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    private function seedMember(string $nik, string $name): Member
    {
        $person = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'national_identity_number' => $nik,
            'full_name' => $name,
            'gender' => 'L',
            'birth_place' => 'Kota Test',
            'birth_date' => '1990-01-01',
            'phone' => '08123456789',
        ]);

        self::$villageIdCounter++;
        $village = OrganizationUnit::query()->create([
            'id' => self::$villageIdCounter,
            'code' => 'V-IND-'.strtoupper(Str::random(4)),
            'name' => 'Desa Pinjaman',
            'type' => 'village',
            'is_active' => true,
        ]);

        return Member::query()->create([
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'M-'.strtoupper(Str::random(6)),
            'registered_at' => '2026-07-01',
            'status' => 'active',
        ]);
    }

    private function seedLoanDirect(string $legacySource, int $productId): int
    {
        $now = '2026-07-22 10:00:00';
        self::$loanIdCounter++;
        $loanId = self::$loanIdCounter;
        DB::connection('tenant')->table('loans')->insert([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'id' => $loanId,
            'loan_number' => strtoupper($legacySource).'-TEST-'.strtoupper(Str::random(6)),
            'loan_product_row_id' => $productId,
            'sequence_number' => $loanId,
            'proposed_at' => '2026-07-20',
            'verified_at' => '2026-07-21',
            'approved_at' => '2026-07-22',
            'funded_at' => '2026-07-22',
            'disbursed_at' => '2026-07-22',
            'principal_amount' => 5000000,
            'service_rate_total' => 18.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'status' => 'active',
            'legacy_source' => $legacySource,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $loanId;
    }

    public function test_individual_installment_route_renders_page(): void
    {
        $member = $this->seedMember('3201000000000001', 'Budi Santoso');
        $loanId = $this->seedLoanDirect('member_loan', $this->productId);

        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => ++self::$borrowerIdCounter,
            'loan_row_id' => $loanId,
            'member_row_id' => $member->row_id,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        $this->actingAs($this->user)
            ->get('/accounting/journal-entries/installment-individual')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/JournalEntries/InstallmentIndividual')
                ->has('loanOptions', 1)
                ->where('loanOptions.0.is_individual', true)
                ->where('loanOptions.0.subject_label', 'Budi Santoso (NIK 3201000000000001)')
                ->has('cashAccounts')
            );
    }

    public function test_individual_installment_route_excludes_group_loans(): void
    {
        $member = $this->seedMember('3201000000000002', 'Siti Aminah');
        $loanId = $this->seedLoanDirect('member_loan', $this->productId);
        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => ++self::$borrowerIdCounter,
            'loan_row_id' => $loanId,
            'member_row_id' => $member->row_id,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        $groupLoanId = $this->seedLoanDirect('group_loan', $this->groupProductId);

        $this->actingAs($this->user)
            ->get('/accounting/journal-entries/installment-individual')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/JournalEntries/InstallmentIndividual')
                ->where('loanOptions', function ($loanOptions) use ($loanId, $groupLoanId) {
                    $values = collect($loanOptions)->pluck('value')->all();
                    self::assertContains($loanId, $values);
                    self::assertNotContains($groupLoanId, $values);

                    return true;
                })
            );
    }

    public function test_group_installment_route_excludes_individual_loans(): void
    {
        $member = $this->seedMember('3201000000000003', 'Andi Wijaya');
        $loanId = $this->seedLoanDirect('member_loan', $this->productId);
        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => ++self::$borrowerIdCounter,
            'loan_row_id' => $loanId,
            'member_row_id' => $member->row_id,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        $groupLoanId = $this->seedLoanDirect('group_loan', $this->groupProductId);

        $this->actingAs($this->user)
            ->get('/accounting/journal-entries/installment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/JournalEntries/Installment')
                ->where('loanOptions', function ($loanOptions) use ($loanId, $groupLoanId) {
                    $values = collect($loanOptions)->pluck('value')->all();
                    self::assertNotContains($loanId, $values);
                    self::assertContains($groupLoanId, $values);

                    return true;
                })
            );
    }

    /**
     * LoanOptions untuk individu harus expose borrower_member_row_id agar
     * frontend bisa auto-fill field `reference` (penyetor = anggota peminjam).
     */
    public function test_individual_loan_options_expose_borrower_member_row_id(): void
    {
        $member = $this->seedMember('3201000000000005', 'Dewi Lestari');
        $loanId = $this->seedLoanDirect('member_loan', $this->productId);
        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => ++self::$borrowerIdCounter,
            'loan_row_id' => $loanId,
            'member_row_id' => $member->row_id,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        $this->actingAs($this->user)
            ->get('/accounting/journal-entries/installment-individual')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/JournalEntries/InstallmentIndividual')
                ->where('loanOptions.0.value', $loanId)
                ->where('loanOptions.0.borrower_member_row_id', (int) $member->row_id)
                ->where('loanOptions.0.is_individual', true)
            );
    }

    /**
     * Submit jurnal angsuran individu harus:
     * 1) Redirect ke halaman individual (bukan halaman kelompok).
     * 2) Membuat journal entry dengan source_type=loan_installment.
     * 3) Insert row loan_installment_tracking dengan borrower member.
     * 4) Update loan_installments.principal_paid & interest_paid (sehingga
     *    Sisa Pokok di dropdown akurat untuk angsuran berikutnya).
     */
    public function test_individual_installment_post_creates_journal_and_updates_installments(): void
    {
        $member = $this->seedMember('3201000000000006', 'Hendra Wijaya');
        $loanId = $this->seedLoanDirect('member_loan', $this->productId);
        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => ++self::$borrowerIdCounter,
            'loan_row_id' => $loanId,
            'member_row_id' => $member->row_id,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        DB::connection('tenant')->table('loan_installments')->insert([
            [
                'tenant_id' => $this->testTenant->row_id,
                'id' => 100,
                'loan_row_id' => $loanId,
                'installment_number' => 1,
                'due_date' => '2026-07-25',
                'principal_due' => 400000,
                'interest_due' => 60000,
                'principal_paid' => 0,
                'interest_paid' => 0,
                'penalty_paid' => 0,
                'component' => 'principal',
                'created_at' => '2026-07-22 10:00:00',
                'updated_at' => '2026-07-22 10:00:00',
            ],
            [
                'tenant_id' => $this->testTenant->row_id,
                'id' => 101,
                'loan_row_id' => $loanId,
                'installment_number' => 1,
                'due_date' => '2026-07-25',
                'principal_due' => 400000,
                'interest_due' => 60000,
                'principal_paid' => 0,
                'interest_paid' => 0,
                'penalty_paid' => 0,
                'component' => 'interest',
                'created_at' => '2026-07-22 10:00:00',
                'updated_at' => '2026-07-22 10:00:00',
            ],
        ]);

        // COA provisioner harus membuat akun kas 1.1.01.x di tabel `accounts`.
        $cashAccount = DB::connection('tenant')
            ->table('accounts')
            ->where('code', 'like', '1.1.01.%')
            ->where('is_postable', true)
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($cashAccount, 'DefaultChartOfAccountsProvisioner harus menyediakan akun kas 1.1.01.x.');

        $response = $this->actingAs($this->user)->post(
            '/accounting/journal-entries/installment-individual',
            [
                'transaction_date' => '2026-07-25',
                'loan_id' => $loanId,
                'installment_number' => 1,
                'principal_amount' => 400000,
                'interest_amount' => 60000,
                'penalty_amount' => 0,
                'cash_account_row_id' => (int) $cashAccount->row_id,
                'description' => 'Angsuran ke-1 Individu a/n Hendra Wijaya',
                'reference' => (int) $member->row_id,
            ]
        );

        $response->assertRedirect(route('accounting.journal-entries.installment-individual'));

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $this->testTenant->row_id,
            'source_type' => 'loan_installment',
            'description' => 'Angsuran ke-1 Individu a/n Hendra Wijaya',
        ], 'tenant');

        // Tracking row untuk peminjam.
        $this->assertDatabaseHas('loan_installment_tracking', [
            'tenant_id' => $this->testTenant->row_id,
            'loan_row_id' => $loanId,
            'installment_number' => 1,
            'member_row_id' => (int) $member->row_id,
            'principal_paid' => 400000,
            'interest_paid' => 60000,
        ], 'tenant');

        // loan_installments harus ter-update agar Sisa Pokok di dropdown akurat.
        $principalRow = DB::connection('tenant')
            ->table('loan_installments')
            ->where('loan_row_id', $loanId)
            ->where('installment_number', 1)
            ->where('component', 'principal')
            ->first();
        $this->assertEquals(400000, (float) $principalRow->principal_paid);

        $interestRow = DB::connection('tenant')
            ->table('loan_installments')
            ->where('loan_row_id', $loanId)
            ->where('installment_number', 1)
            ->where('component', 'interest')
            ->first();
        $this->assertEquals(60000, (float) $interestRow->interest_paid);
    }

    /**
     * Cross-route guard: submit POST ke /installment-individual dengan loan
     * kelompok (legacy_source != 'member_loan') harus ditolak via validation
     * (FormRequest reject loan_id karena legacy_source tidak sesuai).
     */
    public function test_individual_route_rejects_group_loan_submission(): void
    {
        $groupLoanId = $this->seedLoanDirect('group_loan', $this->groupProductId);

        // Cash account harus valid agar hanya loan_id yang di-test error.
        $cashAccount = DB::connection('tenant')
            ->table('accounts')
            ->where('code', 'like', '1.1.01.%')
            ->where('is_postable', true)
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($cashAccount);

        $response = $this->actingAs($this->user)->post(
            '/accounting/journal-entries/installment-individual',
            [
                'transaction_date' => '2026-07-25',
                'loan_id' => $groupLoanId,
                'principal_amount' => 100000,
                'interest_amount' => 10000,
                'cash_account_row_id' => (int) $cashAccount->row_id,
                'description' => 'test',
                'reference' => 1,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('loan_id');
    }

    /**
     * Cross-route guard: submit POST ke /installment (kelompok) dengan loan
     * perorangan (legacy_source = 'member_loan') harus ditolak via validation.
     */
    public function test_group_route_rejects_individual_loan_submission(): void
    {
        $member = $this->seedMember('3201000000000007', 'Test Guard');
        $loanId = $this->seedLoanDirect('member_loan', $this->productId);
        DB::connection('tenant')->table('loan_borrowers')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => ++self::$borrowerIdCounter,
            'loan_row_id' => $loanId,
            'member_row_id' => $member->row_id,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        $cashAccount = DB::connection('tenant')
            ->table('accounts')
            ->where('code', 'like', '1.1.01.%')
            ->where('is_postable', true)
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($cashAccount);

        $response = $this->actingAs($this->user)->post(
            '/accounting/journal-entries/installment',
            [
                'transaction_date' => '2026-07-25',
                'loan_id' => $loanId,
                'principal_amount' => 100000,
                'interest_amount' => 10000,
                'cash_account_row_id' => (int) $cashAccount->row_id,
                'description' => 'test',
                'reference' => (int) $member->row_id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('loan_id');
    }
}
