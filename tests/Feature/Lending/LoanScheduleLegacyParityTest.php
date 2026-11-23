<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\LoanSimulationService;
use App\Domain\Lending\Services\Reports\LoanDocumentService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
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

/**
 * Bukti paritas angka jadwal angsuran dengan database legacy.
 *
 * Parameter diambil dari pinjaman pacuan `rencana_angsuran_3`:
 *  - pinjaman #3235: plafon Rp72.500.000, jangka 12 bulan, jasa 12% total,
 *    pembulatan kecamatan 500.
 *
 * Angka legacy yang harus muncul (lihat PinjamanKelompokController::generate):
 *  - angsuran pokok 1..11 = Rp6.041.500
 *  - angsuran pokok 12     = Rp6.043.500
 *  - angsuran jasa  1..12  = Rp725.000
 *  - total pokok = Rp72.500.000, total jasa = Rp8.700.000
 */
final class LoanScheduleLegacyParityTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Member $member;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Paritas',
            'email' => 'paritas@example.test',
            'username' => 'petugas_paritas',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Paritas',
            'short_name' => 'KPAR',
            'address' => 'Jl. Paritas No. 1',
            'phone' => '081200000000',
            'email' => 'paritas@example.test',
            'installment_rounding' => '5000',
            'disbursement_cutoff_day' => 0,
            'village_installment_day' => 0,
        ]);

        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Paritas',
            'type' => 'village',
            'is_active' => true,
        ]);

        $person = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'full_name' => 'Peminjam Paritas',
            'national_identity_number' => '3201234567890099',
            'gender' => 'm',
        ]);

        $this->member = Member::query()->create([
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'AGT-PAR-001',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();

        $this->productId = (int) DB::connection('tenant')
            ->table('loan_products')
            ->where('code', 'pi')
            ->value('row_id');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_saved_schedule_matches_legacy_loan_3235_numbers(): void
    {
        $loan = $this->createParityLoan();

        $rows = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('installment_number', '>', 0)
            ->orderBy('installment_number')
            ->get()
            ->values();

        self::assertCount(12, $rows);

        // Legacy: 11 baris Rp6.041.500 + baris terakhir Rp6.043.500.
        for ($i = 0; $i < 11; $i++) {
            self::assertSame(6_041_500.0, (float) $rows[$i]->principal_due, 'pokok baris ke-'.($i + 1));
        }
        self::assertSame(6_043_500.0, (float) $rows[11]->principal_due);
        self::assertEqualsWithDelta(72_500_000.0, $rows->sum(fn ($r) => (float) $r->principal_due), 0.01);

        foreach ($rows as $index => $row) {
            self::assertSame(725_000.0, (float) $row->interest_due, 'jasa baris ke-'.($index + 1));
        }
        self::assertEqualsWithDelta(8_700_000.0, $rows->sum(fn ($r) => (float) $r->interest_due), 0.01);
    }

    public function test_documents_show_the_same_saved_numbers(): void
    {
        $loan = $this->createParityLoan();

        // Dokumen berstatus aktif memakai baris jadwal tersimpan (bukan hitung ulang).
        $loan->forceFill(['status' => 'active', 'disbursed_at' => '2026-07-20'])->save();

        $payload = app(LoanDocumentService::class)->payload($loan->fresh(['installments']), 'kartu_angsuran_individu');
        $html = view($payload['document']['view'], $payload)->render();

        self::assertStringContainsString('6,041,500', $html);
        self::assertStringContainsString('6,043,500', $html);
        self::assertStringContainsString('725,000', $html);
        self::assertStringContainsString('72,500,000', $html);
    }

    public function test_group_schedule_uses_same_legacy_numbers_and_split_rows(): void
    {
        [$group, $members] = $this->createGroup();

        $sppId = (int) DB::connection('tenant')
            ->table('loan_products')
            ->where('code', 'spp')
            ->value('row_id');

        $loan = app(LoanService::class)->createProposal([
            'loan_product_id' => $sppId,
            'group_id' => $group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 72_500_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'chair_id' => $members[0]->row_id,
            'secretary_id' => $members[1]->row_id,
            'treasurer_id' => $members[2]->row_id,
            'beneficiary_ids' => [$members[3]->row_id],
        ], (int) $this->user->row_id);

        $rows = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->orderBy('installment_number')
            ->orderBy('component')
            ->get();

        // Tidak ada baris header untuk kelompok; 12 x (pokok + jasa).
        self::assertSame(24, $rows->count());
        self::assertSame(0, $rows->where('installment_number', 0)->count());

        $principalRows = $rows->where('component', 'principal')->sortBy('installment_number')->values();
        $interestRows = $rows->where('component', 'interest')->sortBy('installment_number')->values();

        self::assertCount(12, $principalRows);
        self::assertCount(12, $interestRows);

        for ($i = 0; $i < 11; $i++) {
            self::assertSame(6_041_500.0, (float) $principalRows[$i]->principal_due, 'pokok baris ke-'.($i + 1));
        }
        self::assertSame(6_043_500.0, (float) $principalRows[11]->principal_due);

        foreach ($interestRows as $index => $row) {
            self::assertSame(725_000.0, (float) $row->interest_due, 'jasa baris ke-'.($index + 1));
        }

        self::assertEqualsWithDelta(72_500_000.0, $principalRows->sum(fn ($r) => (float) $r->principal_due), 0.01);
        self::assertEqualsWithDelta(8_700_000.0, $interestRows->sum(fn ($r) => (float) $r->interest_due), 0.01);

        // Dokumen kelompok aktif membaca baris tersimpan (bukan hitung ulang).
        $loan->forceFill(['status' => 'active', 'disbursed_at' => '2026-07-20'])->save();
        $payload = app(LoanDocumentService::class)->payload($loan->fresh(['installments']), 'kartu_angsuran');
        $html = view($payload['document']['view'], $payload)->render();

        self::assertStringContainsString('6,041,500', $html);
        self::assertStringContainsString('6,043,500', $html);
        self::assertStringContainsString('725,000', $html);
    }

    public function test_simulation_matches_the_same_legacy_schedule_numbers(): void
    {
        $result = app(LoanSimulationService::class)->simulate([
            'principal_amount' => 72_500_000,
            'term_months' => 12,
            'interest_rate' => 12,
            'rate_unit' => 'annual',
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'rounding_step' => 500,
            'start_date' => '2026-07-20',
        ]);

        self::assertCount(12, $result['schedule']);

        for ($i = 0; $i < 11; $i++) {
            self::assertSame(6_041_500.0, (float) $result['schedule'][$i]['principal_due']);
        }
        self::assertSame(6_043_500.0, (float) $result['schedule'][11]['principal_due']);

        foreach ($result['schedule'] as $row) {
            self::assertSame(725_000.0, (float) $row['interest_due']);
        }

        self::assertSame(72_500_000.0, (float) $result['summary']['principal_amount']);
        self::assertSame(8_700_000.0, (float) $result['summary']['total_interest']);
        self::assertSame(81_200_000.0, (float) $result['summary']['total_payment']);
    }

    private function createParityLoan(): Loan
    {
        return app(LoanService::class)->createMemberProposal([
            'loan_product_id' => $this->productId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 72_500_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'principal_grace_months' => 0,
            'interest_grace_months' => 0,
            'rounding_step' => 500,
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);
    }

    /**
     * @return array{0: Group, 1: list<Member>}
     */
    private function createGroup(): array
    {
        $group = Group::query()->create([
            'code' => 'KLP-PAR-001',
            'name' => 'Kelompok Paritas',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $members = [];
        foreach (['Ketua', 'Sekretaris', 'Bendahara', 'Pemanfaat'] as $index => $role) {
            $person = Person::query()->create([
                'public_id' => (string) Str::ulid(),
                'full_name' => $role.' Paritas',
                'national_identity_number' => '32012345678910'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'gender' => 'm',
            ]);

            $member = Member::query()->create([
                'public_id' => (string) Str::ulid(),
                'person_row_id' => $person->row_id,
                'organization_unit_row_id' => 1,
                'member_number' => 'AGT-KLP-PAR-'.($index + 1),
                'registered_at' => '2026-01-01',
                'status' => 'active',
            ]);

            GroupMember::query()->create([
                'group_row_id' => $group->row_id,
                'member_row_id' => $member->row_id,
                'joined_at' => '2026-01-01',
                'status' => 'active',
            ]);

            $members[] = $member;
        }

        foreach (['chair', 'secretary', 'treasurer'] as $index => $position) {
            GroupOfficer::query()->create([
                'group_row_id' => $group->row_id,
                'member_row_id' => $members[$index]->row_id,
                'position' => $position,
                'started_at' => '2026-01-01',
            ]);
        }

        return [$group, $members];
    }
}
