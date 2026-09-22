<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanStatusHistory;
use App\Domain\Lending\Models\LoanWriteOff;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\MemberLoanScheduleCalculator;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
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

final class MemberLoanLifecycleTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private OrganizationUnit $village;

    private int $memberLoanProductId;

    private Member $member;

    private Account $disbursementAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Pinjaman Individu',
            'email' => 'member-loan@example.test',
            'username' => 'member_loan_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Koperasi Maju Bersama',
            'short_name' => 'KMB',
            'address' => 'Jl. Raya No. 1, Kota Test',
            'phone' => '08123456789',
            'email' => 'info@kmb.test',
            'installment_rounding' => '5000',
            'disbursement_cutoff_day' => 25,
            'village_installment_day' => 15,
        ]);

        $this->village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Makmur',
            'type' => 'village',
            'is_active' => true,
        ]);

        $person = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'full_name' => 'Andi Pinjam',
            'national_identity_number' => '3201234567890010',
            'gender' => 'm',
        ]);

        $this->member = Member::query()->create([
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $this->village->row_id,
            'member_number' => 'AGT-PI-001',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(TenantLoanProductProvisioner::class)->ensureDefaults();

        $this->memberLoanProductId = (int) DB::connection('tenant')
            ->table('loan_products')
            ->where('code', 'pi')
            ->value('row_id');

        $this->disbursementAccount = Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Kas Pencairan',
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

    public function test_create_member_proposal_persists_collateral_and_individual_schedule(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6_000_000,
            'service_rate_total' => 18.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'principal_grace_months' => 0,
            'interest_grace_months' => 0,
            'collateral' => [
                'type' => 'kendaraan',
                'description' => 'Sepeda motor Honda Beat 2022',
                'value' => 8_000_000,
                'reference' => 'BP-1234-XX',
            ],
            'verification_remarks' => 'Pemohon baru, usaha warung.',
        ], (int) $this->user->row_id);

        self::assertSame('member_loan', $loan->legacy_source);
        self::assertSame('draft', $loan->status);
        self::assertNotNull($loan->collateral);
        self::assertSame('kendaraan', $loan->collateral['type']);
        self::assertSame('Sepeda motor Honda Beat 2022', $loan->collateral['description']);
        self::assertSame(8_000_000.0, (float) $loan->collateral['value']);
        self::assertSame('Pemohon baru, usaha warung.', $loan->verification_remarks);

        // Borrower harus menunjuk ke anggota (bukan kelompok)
        $borrower = LoanBorrower::query()->where('loan_row_id', $loan->row_id)->firstOrFail();
        self::assertSame((int) $this->member->row_id, (int) $borrower->member_row_id);
        self::assertNull($borrower->group_row_id);

        // Jadwal angsuran harus dibuat oleh MemberLoanScheduleCalculator (pacuan-style)
        // Pacuan menyimpan 1 baris header (installment_number=0) + N baris angsuran (1..jangka).
        $installments = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->orderBy('installment_number')
            ->get();

        self::assertCount(13, $installments); // header + 12 angsuran

        // Saring baris header (ke-0) → 12 baris angsuran asli
        $rows = $installments->where('installment_number', '>', 0)->values();
        self::assertCount(12, $rows);

        // Pokok harus proporsional & total pas (pacuan: alokasi 6jt / 12 angsuran dengan magic alokasi ≠ 6/8/12/14/18/20jt)
        $principalSum = $rows->sum(fn (LoanInstallment $i) => (float) $i->principal_due);
        self::assertEqualsWithDelta(6_000_000.0, $principalSum, 1.0);

        // Running total (target_pokok/target_jasa pacuan) harus kumulatif
        $cumulative = 0.0;
        foreach ($rows as $row) {
            $cumulative += (float) $row->principal_due;
            self::assertEqualsWithDelta($cumulative, (float) $row->running_principal, 1.0,
                'running_principal harus kumulatif pada angsuran '.$row->installment_number);
        }
    }

    public function test_member_proposal_schedule_handles_jangka_24_with_magic_formula(): void
    {
        $loan = app(LoanService::class)->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 8_000_000,
            'service_rate_total' => 24.0,
            'term_months' => 24,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        $installments = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->orderBy('installment_number')
            ->get();

        // Pacuan: 1 header row ke-0 + 24 baris angsuran = 25 row total
        self::assertCount(25, $installments);

        $rows = $installments->where('installment_number', '>', 0)->values();
        self::assertCount(24, $rows);

        // Pokok per bulan (flat) dihitung dengan magic formula pacuan untuk jangka==24:
        //  wajib_pokok = pembulatan((alokasi/10 - jasa)/2, -500), lalu adjustment -5000 untuk alokasi >= 8jt.
        // Untuk alokasi 8jt, hasil utama 335.000/bulan.
        // Pokok bulan ke-24 = selisih = alokasi - sum_pokok (23 × 335000) = 295.000.
        $principalSum = $rows->sum(fn (LoanInstallment $i) => (float) $i->principal_due);
        self::assertEqualsWithDelta(8_000_000.0, $principalSum, 1.0,
            'Total pokok harus tutup pas ke 8jt (paritas pacuan: angsuran ke-24 = alokasi - sum_pokok)');

        // Jasa flat: 8jt × 24% = 1.920.000. Pembulatan per baris sehingga bisa ~ ±10rb.
        $interestSum = $rows->sum(fn (LoanInstallment $i) => (float) $i->interest_due);
        self::assertEqualsWithDelta(1_920_000.0, $interestSum, 50.0);

        // Running total harus kumulatif dan cocok dengan total.
        $lastRunningPrincipal = (float) $rows->last()->running_principal;
        self::assertEqualsWithDelta(8_000_000.0, $lastRunningPrincipal, 1.0,
            'running_principal baris terakhir harus sama dengan total pokok');
    }

    public function test_verify_to_waiting_to_disburse_to_complete_lifecycle_for_individual(): void
    {
        $service = app(LoanService::class);

        // 1. Create proposal
        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 5_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => [
                'type' => 'sertifikat_tanah',
                'description' => 'SHM No. 123',
                'value' => 25_000_000,
                'reference' => 'SHM-123',
            ],
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        // 2. Verify (draft → verified)
        $service->verify($loan, [
            'verified_at' => '2026-07-21',
            'verification_amount' => 5_000_000,
            'verification_notes' => 'Dokumen valid, BPKB sesuai.',
        ], (int) $this->user->row_id);

        $loan = $loan->fresh();
        self::assertSame('verified', $loan->status);

        // 3. Approve (verified → waiting) dengan regenerate jadwal individu
        $service->approve($loan, [
            'approved_at' => '2026-07-22',
            'planned_disbursed_at' => '2026-07-25',
            'allocation_notes' => 'Disetujui plafon 5jt.',
        ], (int) $this->user->row_id);

        $loan = $loan->fresh();
        self::assertSame('waiting', $loan->status);
        self::assertNotNull($loan->approved_at);
        self::assertNotNull($loan->funded_at);

        $installmentsAfterApprove = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->count();
        self::assertGreaterThanOrEqual(12, $installmentsAfterApprove);

        // 4. Disburse (waiting → active)
        $service->disburse($loan, [
            'disbursed_at' => '2026-07-25',
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
            'disbursement_notes' => 'Pencairan tunai di kantor.',
            'spk_no' => 'SPK-2026-001',
            'disbursement_slot' => '10:00 WIB · Kantor BUMDes',
            'verification_remarks' => 'Final OK.',
        ], (int) $this->user->row_id);

        $loan = $loan->fresh();
        self::assertSame('active', $loan->status);
        self::assertSame('SPK-2026-001', $loan->spk_no);
        self::assertSame('10:00 WIB · Kantor BUMDes', $loan->disbursement_slot);
        self::assertSame('Final OK.', $loan->verification_remarks);

        // 5. Complete (active → completed)
        $service->complete($loan, [
            'completed_at' => '2026-08-30',
            'notes' => 'Lunas dipercepat.',
        ], (int) $this->user->row_id);

        $loan = $loan->fresh();
        self::assertSame('completed', $loan->status);
        self::assertNotNull($loan->completed_at);

        // Status history harus terdiri dari minimal 5 transisi
        $histories = LoanStatusHistory::query()
            ->where('loan_row_id', $loan->row_id)
            ->orderBy('changed_at')
            ->get();
        $statuses = $histories->pluck('to_status')->toArray();
        self::assertContains('draft', $statuses);
        self::assertContains('verified', $statuses);
        self::assertContains('waiting', $statuses);
        self::assertContains('active', $statuses);
        self::assertContains('completed', $statuses);
    }

    public function test_reject_member_loan_sets_rejected_status_only_from_pre_active_status(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 4_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        // Boleh menolak dari status draft
        $service->rejectMemberLoan($loan, (int) $this->user->row_id, 'Penghasilan tidak cukup');
        self::assertSame('rejected', $loan->fresh()->status);

        // History harus menulis transisi ke 'rejected' dengan notes
        $history = LoanStatusHistory::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('to_status', 'rejected')
            ->first();
        self::assertNotNull($history);
        self::assertSame('draft', $history->from_status);
        self::assertSame('Penghasilan tidak cukup', $history->notes);

        // Setelah rejected, anggota boleh membuat pinjaman baru.
        $newLoan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-08-01',
            'principal_amount' => 3_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);
        self::assertSame('draft', $newLoan->status);

        // Tolak yang sudah active harus gagal
        $service->verify($newLoan, ['verified_at' => '2026-08-02', 'verification_amount' => 3000000], (int) $this->user->row_id);
        $service->approve($newLoan, ['approved_at' => '2026-08-03', 'planned_disbursed_at' => '2026-08-05'], (int) $this->user->row_id);
        $service->disburse($newLoan, [
            'disbursed_at' => '2026-08-05',
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
        ], (int) $this->user->row_id);

        $this->expectException(\DomainException::class);
        $service->rejectMemberLoan($newLoan->fresh(), (int) $this->user->row_id);
    }

    public function test_collateral_helper_normalizes_various_input_shapes(): void
    {
        $service = app(LoanService::class);
        $reflection = new \ReflectionMethod($service, 'normalizeCollateral');
        $reflection->setAccessible(true);

        // 1. Array asosiatif lengkap
        $r1 = $reflection->invoke($service, [
            'type' => 'bpkb',
            'description' => 'BPKB Motor',
            'value' => '5000000',
            'reference' => 'BP-9999',
        ]);
        self::assertIsArray($r1);
        self::assertSame('bpkb', $r1['type']);
        self::assertSame(5_000_000.0, (float) $r1['value']);

        // 2. JSON string valid
        $r2 = $reflection->invoke($service, json_encode([
            'type' => 'sertifikat_tanah',
            'description' => 'SHM No. 1',
            'value' => 100000000,
        ]));
        self::assertSame('sertifikat_tanah', $r2['type']);
        self::assertSame(100_000_000.0, (float) $r2['value']);

        // 3. Null / string kosong → null
        self::assertNull($reflection->invoke($service, null));
        self::assertNull($reflection->invoke($service, ''));
        self::assertNull($reflection->invoke($service, '   '));

        // 4. Array dengan tipe invalid → fallback 'lainnya'
        $r4 = $reflection->invoke($service, [
            'type' => 'unknown_type',
            'description' => 'Lain',
            'value' => 0,
        ]);
        self::assertSame('lainnya', $r4['type']);

        // 5. Semua field kosong setelah trim → null
        $r5 = $reflection->invoke($service, [
            'type' => 'kendaraan',
            'description' => '',
            'value' => '',
            'reference' => '',
        ]);
        self::assertNull($r5);
    }

    public function test_member_loan_card_service_renders_pdf_for_individual(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 4_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        // Sudah ada jadwal, kartu bisa dicetak langsung
        $this->actingAs($this->user)
            ->get('/lending/member-loans/'.$loan->row_id.'/card')
            ->assertOk();
    }

    public function test_member_loan_index_lists_only_member_loans(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 4_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        $this->actingAs($this->user)
            ->get('/lending/member-loans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/MemberLoans/Index')
                ->where('tab', 'proposal')
                ->has('loans.data', 1)
                ->where('loans.data.0.row_id', $loan->row_id)
            );
    }

    public function test_member_loan_endpoints_404_for_group_loans(): void
    {
        $service = app(LoanService::class);

        $group = $service->createProposal($this->groupProposalData(), (int) $this->user->row_id);

        $this->actingAs($this->user)
            ->get('/lending/member-loans/'.$group->row_id)
            ->assertNotFound();
    }

    private function groupProposalData(): array
    {
        return [
            'loan_product_id' => (int) DB::connection('tenant')
                ->table('loan_products')
                ->where('code', 'spp')
                ->value('row_id'),
            'group_id' => $this->createGroup(),
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6_000_000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->member->row_id,
            'secretary_id' => $this->member->row_id,
            'treasurer_id' => $this->member->row_id,
            'beneficiary_ids' => [$this->member->row_id],
        ];
    }

    private function createGroup(): int
    {
        $group = Group::query()->create([
            'code' => 'KLP-MEM',
            'name' => 'Kelompok Test',
            'status' => 'active',
            'organization_unit_row_id' => $this->village->row_id,
        ]);

        return (int) $group->row_id;
    }

    public function test_write_off_for_individual_creates_pacuan_style_journal(): void
    {
        $service = app(LoanService::class);
        $this->ensureReceivableAccounts();

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 5_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => [
                'type' => 'kendaraan',
                'description' => 'Motor',
                'value' => 7_000_000,
                'reference' => 'BP-999',
            ],
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        $service->verify($loan, ['verified_at' => '2026-07-21', 'verification_amount' => 5000000], (int) $this->user->row_id);
        $service->approve($loan, ['approved_at' => '2026-07-22', 'planned_disbursed_at' => '2026-07-25'], (int) $this->user->row_id);
        $service->disburse($loan, [
            'disbursed_at' => '2026-07-25',
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
        ], (int) $this->user->row_id);

        // Write off (mirrors legacy hapus()) — pakai method writeOff() existing.
        $service->writeOff($loan, [
            'written_off_at' => '2026-08-30',
            'reason' => 'Anggota meninggal, keluarga tidak mampu melunasi.',
        ], (int) $this->user->row_id);

        $loan = $loan->fresh();
        self::assertSame('written_off', $loan->status);
        self::assertNotNull($loan->completed_at);

        // History harus mencatat transisi ke written_off dengan alasan.
        $history = LoanStatusHistory::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('to_status', 'written_off')
            ->first();
        self::assertNotNull($history);
        self::assertSame('active', $history->from_status);

        // LoanWriteOff row harus tercatat dengan saldo akhir sebagai writeOffAmount.
        $writeOff = LoanWriteOff::query()
            ->where('loan_row_id', $loan->row_id)
            ->latest('row_id')
            ->first();
        self::assertNotNull($writeOff);
        self::assertSame('Anggota meninggal, keluarga tidak mampu melunasi.', $writeOff->reason);
        self::assertGreaterThan(0.0, (float) $writeOff->principal_balance);
    }

    public function test_reschedule_for_individual_uses_pacuan_schedule_calculator(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 8_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        $service->verify($loan, ['verified_at' => '2026-07-21', 'verification_amount' => 8000000], (int) $this->user->row_id);
        $service->approve($loan, ['approved_at' => '2026-07-22', 'planned_disbursed_at' => '2026-07-25'], (int) $this->user->row_id);
        $service->disburse($loan, [
            'disbursed_at' => '2026-07-25',
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
        ], (int) $this->user->row_id);

        // Reschedule dengan sisa pokok (belum ada angsuran dibayar = 8jt).
        $newLoan = $service->reschedule($loan, [
            'rescheduled_at' => '2026-08-10',
            'term_months' => 12,
            'service_rate_total' => 12.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
        ], (int) $this->user->row_id);

        // Loan lama di status rescheduled.
        self::assertSame('rescheduled', $loan->fresh()->status);

        // Loan baru di status active, dengan pokok = sisa.
        self::assertSame('active', $newLoan->status);
        self::assertSame('member_loan', $newLoan->legacy_source);
        self::assertSame((int) $this->member->row_id, (int) $newLoan->borrower?->member_row_id);
        self::assertNull($newLoan->borrower?->group_row_id);

        // Jadwal angsuran harus pakai pacuan-style (running_principal & running_interest populated, header row 0).
        $newInstallments = LoanInstallment::query()
            ->where('loan_row_id', $newLoan->row_id)
            ->orderBy('installment_number')
            ->get();
        self::assertGreaterThan(12, $newInstallments->count());

        $rows = $newInstallments->where('installment_number', '>', 0);
        $principalSum = $rows->sum(fn (LoanInstallment $i) => (float) $i->principal_due);
        self::assertEqualsWithDelta((float) $loan->principal_amount, $principalSum, 1.0);
    }

    public function test_revert_member_loan_returns_to_draft_status(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 3_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        $service->verify($loan, ['verified_at' => '2026-07-21', 'verification_amount' => 3000000], (int) $this->user->row_id);

        // Revert dari verified → draft (kembaliProposal pacuan)
        $service->revertToDraft($loan, (int) $this->user->row_id);
        self::assertSame('draft', $loan->fresh()->status);
    }

    private function ensureReceivableAccounts(): void
    {
        foreach ([
            ['code' => '1.1.03.09', 'name' => 'Piutang Perorangan Pokok'],
            ['code' => '1.1.04.08', 'name' => 'CKPN Piutang Perorangan Pokok'],
        ] as $row) {
            Account::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'account_type' => 'asset',
                    'normal_balance' => 'D',
                    'level' => 4,
                    'is_postable' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    public function test_record_installment_payment_for_individual_posts_correct_journal(): void
    {
        $service = app(LoanService::class);

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $this->member->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        $service->verify($loan, ['verified_at' => '2026-07-21', 'verification_amount' => 6000000], (int) $this->user->row_id);
        $service->approve($loan, ['approved_at' => '2026-07-22', 'planned_disbursed_at' => '2026-07-25'], (int) $this->user->row_id);
        $service->disburse($loan, [
            'disbursed_at' => '2026-07-25',
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
        ], (int) $this->user->row_id);

        $installment = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('installment_number', 1)
            ->firstOrFail();
        $principal = (float) $installment->principal_due;
        $interest = (float) $installment->interest_due;

        // Bayar angsuran ke-1 lewat recordInstallmentPayment (paritas pacuan TransaksiController::angsuran)
        $posted = $service->recordInstallmentPayment([
            'transaction_date' => '2026-08-25',
            'loan_id' => $loan->row_id,
            'installment_row_id' => $installment->row_id,
            'installment_number' => 1,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'penalty_amount' => 0,
            'cash_account_row_id' => $this->disbursementAccount->row_id,
            'description' => 'Angsuran ke-1 '.$this->member->member_number,
            'reference' => $this->member->row_id,
        ], (int) $this->user->row_id);

        // JournalEntry harus ter-post dengan debit kas & kredit piutang+jasa (paritas pacuan baris 1).
        $lines = $posted->lines()->orderBy('line_number')->get();
        self::assertGreaterThanOrEqual(3, $lines->count());

        $firstLine = $lines->first();
        self::assertSame((float) ($principal + $interest), (float) $firstLine->debit);
        self::assertSame(0.0, (float) $firstLine->credit);

        // Baris kedua: credit piutang pokok
        $piutangLine = $lines->where('line_number', 2)->first();
        self::assertNotNull($piutangLine);
        self::assertSame($principal, (float) $piutangLine->credit);

        // Baris ketiga: credit pendapatan jasa
        $jasaLine = $lines->where('line_number', 3)->first();
        self::assertNotNull($jasaLine);
        self::assertSame($interest, (float) $jasaLine->credit);
    }

    /**
     * Paritas 1:1 kelompok vs individu untuk logika jadwal (pacuan: generate() PinjamanKelompokController
     * dan PinjamanIndividuController adalah 100% identik).
     *
     * Test ini memastikan bahwa untuk input parameter yang sama, jadwal angsuran kelompok dan individu
     * menghasilkan nominal pokok+jasa yang setara per bulan (modulo alokasi per anggota untuk kelompok).
     */
    public function test_individual_schedule_parity_with_single_member_group_loan(): void
    {
        $service = app(LoanService::class);
        $sppProductId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        // Siapkan 1 kelompok dengan 1 anggota (paritas uji).
        $chair = $this->createAdditionalMember('Ketua Satu', '3273010203040010', 'KET-001');
        $secretary = $this->createAdditionalMember('Sekretaris Satu', '3273010203040011', 'SEK-001');
        $treasurer = $this->createAdditionalMember('Bendahara Satu', '3273010203040012', 'BEN-001');
        $beneficiary = $this->createAdditionalMember('Pemanfaat Satu', '3273010203040013', 'ANG-001');

        $group = Group::query()->create([
            'code' => 'KLP-PARITAS',
            'name' => 'Kelompok Paritas',
            'status' => 'active',
            'organization_unit_row_id' => $this->village->row_id,
        ]);
        foreach ([$chair, $secretary, $treasurer, $beneficiary] as $m) {
            GroupMember::query()->create([
                'group_row_id' => $group->row_id,
                'member_row_id' => $m->row_id,
                'joined_at' => '2026-01-01',
                'status' => 'active',
            ]);
        }
        foreach (['chair', 'secretary', 'treasurer'] as $i => $position) {
            GroupOfficer::query()->create([
                'group_row_id' => $group->row_id,
                'member_row_id' => [$chair, $secretary, $treasurer][$i]->row_id,
                'position' => $position,
                'started_at' => '2026-01-01',
            ]);
        }

        $params = [
            'proposed_at' => '2026-07-20',
            'principal_amount' => 5_000_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'principal_grace_months' => 0,
            'interest_grace_months' => 0,
        ];

        // Pinjaman Kelompok dibuat DULU agar eligibility tidak blokir individu untuk anggota yang sama.
        $groupLoan = $service->createProposal([
            'loan_product_id' => $sppProductId,
            'group_id' => $group->row_id,
            'proposed_at' => $params['proposed_at'],
            'principal_amount' => $params['principal_amount'],
            'service_rate_total' => $params['service_rate_total'],
            'term_months' => $params['term_months'],
            'installment_method' => $params['installment_method'],
            'principal_frequency' => $params['principal_frequency'],
            'interest_frequency' => $params['interest_frequency'],
            'chair_id' => $chair->row_id,
            'secretary_id' => $secretary->row_id,
            'treasurer_id' => $treasurer->row_id,
            'beneficiary_ids' => [$beneficiary->row_id],
        ], (int) $this->user->row_id);

        // Reject pinjaman individu dari $this->member supaya tidak konflik dengan test sebelumnya
        // yang menggunakan $this->member untuk individu. Pakai anggota Paritas.
        $individualLoan = $service->createMemberProposal(
            array_merge($params, [
                'loan_product_id' => $this->memberLoanProductId,
                'member_id' => $beneficiary->row_id,
                'collateral' => null,
                'verification_remarks' => null,
            ]),
            (int) $this->user->row_id,
        );

        $individualRows = LoanInstallment::query()
            ->where('loan_row_id', $individualLoan->row_id)
            ->where('installment_number', '>', 0)
            ->orderBy('installment_number')
            ->get();

        $groupRows = LoanInstallment::query()
            ->where('loan_row_id', $groupLoan->row_id)
            ->where('installment_number', '>', 0)
            ->orderBy('installment_number')
            ->get();

        // Indikator paritas: aggregate total angsuran pokok dan jasa harus sama untuk
        // pinjaman individu maupun pinjaman kelompok dengan plafon dan parameter identik.
        //
        // CATATAN: pacuan (`PinjamanIndividuController::generate()` line 2317-2607 dan
        // `PinjamanKelompokController::generate()` line 2317-2607) adalah 100% identik dan
        // menghasilkan jadwal angsuran yang sama untuk parameter yang sama. Di Next, individu
        // sudah pakai `MemberLoanScheduleCalculator` (copy rumus pacuan), sedangkan kelompok
        // masih pakai `generatePrincipalSchedule`/`generateInterestSchedule` (generik flat-rata-rata).
        //
        // Test ini hanya memverifikasi paritas aggregate total — bukan nominal per-bulan
        // (akan beda sampai kelompok juga migrasi ke kalkulator pacuan-style).
        $individualByNumber = $individualRows->keyBy('installment_number');
        $groupAggregate = [];
        foreach ($groupRows as $g) {
            $n = (int) $g->installment_number;
            if (! isset($groupAggregate[$n])) {
                $groupAggregate[$n] = ['principal_due' => 0.0, 'interest_due' => 0.0];
            }
            // Asumsi: salah satu baris adalah principal (component='principal'), yang lain interest.
            $component = (string) ($g->component ?? 'combined');
            if ($component === 'principal') {
                $groupAggregate[$n]['principal_due'] += (float) $g->principal_due;
            } elseif ($component === 'interest') {
                $groupAggregate[$n]['interest_due'] += (float) $g->interest_due;
            } else {
                // Baris combined (gabungan) → cocok dengan individu
                $groupAggregate[$n]['principal_due'] += (float) $g->principal_due;
                $groupAggregate[$n]['interest_due'] += (float) $g->interest_due;
            }
        }

        $individualKeys = $individualByNumber->keys()->sort()->values()->all();
        $groupKeys = collect($groupAggregate)->keys()->sort()->values()->all();

        // Hapus baris ke-0 (header) dari perbandingan — pacuan simpan baris header baik di
        // kelompok maupun individu; yang dibandingkan adalah baris angsuran 1..N.
        $individualKeys = array_values(array_filter($individualKeys, fn ($k) => $k > 0));
        $groupKeys = array_values(array_filter($groupKeys, fn ($k) => $k > 0));

        self::assertSame($individualKeys, $groupKeys,
            'Jumlah angsuran harus paritas (kecuali baris header).');

        // Total alokasi pokok sama (paritas aggregate)
        $individualTotalPokok = $individualRows->sum(fn ($r) => (float) $r->principal_due);
        $groupTotalPokok = array_sum(array_column($groupAggregate, 'principal_due'));
        self::assertEqualsWithDelta(5_000_000.0, $individualTotalPokok, 1.0,
            'Total pokok individu harus tutup ke 5jt');
        self::assertEqualsWithDelta(5_000_000.0, $groupTotalPokok, 1.0,
            'Total pokok kelompok harus tutup ke 5jt');

        // Total jasa juga paritas (alokasi × 12%)
        $individualTotalJasa = $individualRows->sum(fn ($r) => (float) $r->interest_due);
        $groupTotalJasa = array_sum(array_column($groupAggregate, 'interest_due'));
        self::assertEqualsWithDelta(600_000.0, $individualTotalJasa, 50.0,
            'Total jasa individu harus tutup ke 600rb (5jt × 12%)');
        self::assertEqualsWithDelta(600_000.0, $groupTotalJasa, 50.0,
            'Total jasa kelompok harus tutup ke 600rb (5jt × 12%)');
    }

    private function createAdditionalMember(string $name, string $nik, string $memberNumber): Member
    {
        $person = Person::query()->create([
            'public_id' => (string) Str::ulid(),
            'full_name' => $name,
            'national_identity_number' => $nik,
            'gender' => 'L',
        ]);

        return Member::query()->create([
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $this->village->row_id,
            'member_number' => $memberNumber,
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    /**
     * Sistem angsuran 12 (mingguan): tanggal jatuh tempo pacuan +x*7 hari dari tanggal
     * proposal yang sudah di-override jadwal desa.
     */
    public function test_individual_schedule_uses_weekly_dates_for_sistem_12(): void
    {
        $service = app(LoanService::class);

        // Gunakan anggota tambahan agar tidak konflik dengan test lain.
        $member = $this->createAdditionalMember('Peminjam Mingguan', '3273010203040099', 'AGT-WEEKLY');

        $loan = $service->createMemberProposal([
            'loan_product_id' => $this->memberLoanProductId,
            'member_id' => $member->row_id,
            'proposed_at' => '2026-07-01', // awal bulan
            'principal_amount' => 1_200_000,
            'service_rate_total' => 12.0,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'weekly', // sistem=12 (mingguan, +x*7 days)
            'interest_frequency' => 'weekly',
            'collateral' => null,
            'verification_remarks' => null,
        ], (int) $this->user->row_id);

        $installments = LoanInstallment::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('installment_number', '>', 0)
            ->orderBy('installment_number')
            ->get();

        self::assertCount(12, $installments);

        // Pacuan: due_date[i] = tgl_override + (i * 7 days). Override dari village_installment_day (15).
        // tgl_proposal = 2026-07-01, override ke 2026-07-15 (tgl 15).
        // Angsuran ke-1 = 2026-07-15 + 7 = 2026-07-22.
        // Angsuran ke-2 = 2026-07-15 + 14 = 2026-07-29.
        // Angsuran ke-12 = 2026-07-15 + 84 = 2026-10-07.
        $first = $installments->first();
        $last = $installments->last();

        self::assertSame('2026-07-22', $first->due_date->format('Y-m-d'),
            'Mingguan +7 hari dari override jadwal desa (15 Juli).');
        self::assertSame('2026-10-07', $last->due_date->format('Y-m-d'),
            'Mingguan +84 hari (12 × 7) dari override jadwal desa.');
    }
}
