<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBeneficiaryWriteOff;
use App\Domain\Lending\Models\LoanInstallmentTracking;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
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

final class LoanBeneficiaryWriteOffTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private Member $chair;

    private Member $secretary;

    private Member $treasurer;

    /** @var Member[] */
    private array $beneficiaries = [];

    private Account $disbursementAccount;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas WriteOff Pemanfaat',
            'email' => 'beneficiary-writeoff@example.test',
            'username' => 'beneficiary_writeoff_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create(['id' => 1, 'code' => 'V001', 'name' => 'Desa Induk', 'type' => 'village', 'is_active' => true]);

        $this->group = Group::query()->create([
            'code' => 'KLP-BWO',
            'name' => 'Kelompok Penghapusan Pemanfaat',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $this->chair = $this->createMember('Budi Ketua', '3273010203040101', 'KET-BWO');
        $this->secretary = $this->createMember('Siti Sekretaris', '3273010203040102', 'SEK-BWO');
        $this->treasurer = $this->createMember('Andi Bendahara', '3273010203040103', 'BEN-BWO');
        $this->beneficiaries = [
            $this->createMember('Dewi Pemanfaat', '3273010203040104', 'ANG-BWO1'),
            $this->createMember('Eka Pemanfaat', '3273010203040105', 'ANG-BWO2'),
            $this->createMember('Fajar Pemanfaat', '3273010203040106', 'ANG-BWO3'),
        ];

        GroupOfficer::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $this->chair->row_id, 'position' => 'chair', 'started_at' => '2026-01-01']);
        GroupOfficer::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $this->secretary->row_id, 'position' => 'secretary', 'started_at' => '2026-01-01']);
        GroupOfficer::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $this->treasurer->row_id, 'position' => 'treasurer', 'started_at' => '2026-01-01']);

        foreach ([$this->chair, $this->secretary, $this->treasurer, ...$this->beneficiaries] as $member) {
            GroupMember::query()->create(['group_row_id' => $this->group->row_id, 'member_row_id' => $member->row_id, 'joined_at' => '2026-01-01', 'status' => 'active']);
        }

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

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

    public function test_happy_path_write_off_at_period_5_of_10_records_journal_and_inserts_row(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $targetBeneficiary = $loan->beneficiaries->first();
        $allocatedAmount = (float) $targetBeneficiary->allocated_amount;

        // Simulate some principal payments for the target beneficiary (4 periods × 1/10 of allocation).
        $this->recordPartialPrincipalPayments($loan, $targetBeneficiary->member_row_id, 4, $allocatedAmount / 10);

        $principalBeforeModify = (float) $loan->installments
            ->where('component', 'principal')
            ->sum('principal_due');

        $principalRemainingBefore = (float) $loan->installments
            ->where('component', 'principal')
            ->sum(fn ($i) => (float) $i->principal_due - (float) $i->principal_paid);

        $response = $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$targetBeneficiary->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Anggota meninggal dunia.',
                'installment_number' => 5,
            ]);

        $response->assertRedirect('/lending/loans/'.$loan->row_id);

        $fresh = $loan->fresh(['installments', 'beneficiaries']);
        self::assertSame('active', $fresh->status, 'Loan status harus tetap aktif (write-off per beneficiary tidak menutup loan).');

        // 1) Beneficiary marked.
        $beneficiary = $fresh->beneficiaries->firstWhere('member_row_id', $targetBeneficiary->member_row_id);
        self::assertNotNull($beneficiary->written_off_at);
        self::assertSame($allocatedAmount * 0.6, (float) $beneficiary->written_off_amount);

        // 2) Audit row inserted.
        $audit = LoanBeneficiaryWriteOff::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('member_row_id', $targetBeneficiary->member_row_id)
            ->first();
        self::assertNotNull($audit);
        self::assertSame($allocatedAmount * 0.6, (float) $audit->principal_balance);
        self::assertSame(5, (int) $audit->installment_number);
        self::assertSame('Anggota meninggal dunia.', $audit->reason);

        // 3) Journal entry created & posted.
        $journal = JournalEntry::query()->find($audit->journal_entry_row_id);
        self::assertNotNull($journal);
        self::assertSame('loan_beneficiary_write_off', $journal->source_type);
        self::assertSame('hapus_bukuan_pemanfaat', $journal->transaction_type);
        self::assertSame('posted', $journal->status);

        // 4) Schedule row count increased by 1 (was 20 principal+interest, now 21).
        $principalRows = $fresh->installments->where('component', 'principal');
        $interestRows = $fresh->installments->where('component', 'interest');
        $writeOffRows = $fresh->installments->where('component', 'write_off');
        self::assertSame(10, $principalRows->count(), 'Principal rows tetap 10 setelah write-off.');
        self::assertSame(10, $interestRows->count(), 'Interest rows tetap 10.');
        self::assertSame(1, $writeOffRows->count(), 'Write-off row harus ter-insert tepat 1 row.');

        // 5) Write-off row at installment_number = 6.
        $writeOffRow = $writeOffRows->first();
        self::assertSame(6, (int) $writeOffRow->installment_number);
        self::assertSame($allocatedAmount * 0.6, (float) $writeOffRow->principal_due);
        self::assertSame((float) $writeOffRow->principal_due, (float) $writeOffRow->principal_paid);
        self::assertSame('paid', $writeOffRow->status);

        // 6) Principal rows after position 5 renumbered to 6,7,8,9,10 (then shifted to 7,8,9,10,11).
        $principalNumbers = $principalRows->pluck('installment_number')->map(fn ($v) => (int) $v)->sort()->values()->all();
        self::assertSame([1, 2, 3, 4, 5, 7, 8, 9, 10, 11], $principalNumbers, 'Principal 1-5 tetap, 6-10 harus geser menjadi 7-11.');

        // 7) Total principal_due (principal + write_off) setelah write-off ≈ original (write_off row
        //    absorbs the reduction di baris 7-11). Toleransi rounding drift ±0.01.
        $principalAfter = (float) $fresh->installments
            ->whereIn('component', ['principal', 'write_off'])
            ->sum('principal_due');
        self::assertEqualsWithDelta($principalBeforeModify, $principalAfter, 0.01, 'Total principal_due harus sama (write_off row absorbs reduction).');

        // 8) loan.principal_remaining (group perspective) berkurang sebesar writeOffAmount.
        $principalRemainingAfter = (float) $fresh->installments
            ->whereIn('component', ['principal', 'write_off'])
            ->sum(fn ($i) => (float) $i->principal_due - (float) $i->principal_paid);
        $writeOffAmount = $allocatedAmount * 0.6;
        self::assertEqualsWithDelta($principalRemainingBefore - $writeOffAmount, $principalRemainingAfter, 0.01, 'principal_remaining harus turun sebesar writeOffAmount.');
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $target = $loan->beneficiaries->first();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => '',
                'reason' => '',
                'installment_number' => '',
            ])
            ->assertSessionHasErrors(['written_off_at', 'reason', 'installment_number']);

        self::assertSame('active', $loan->fresh()->status);
        self::assertSame(0, LoanBeneficiaryWriteOff::query()->where('loan_row_id', $loan->row_id)->count());
    }

    public function test_rejects_non_active_loan(): void
    {
        $loan = $this->createProposal(10, 6_000_000);
        $target = $loan->beneficiaries->first();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Coba hapus.',
                'installment_number' => 5,
            ])
            ->assertRedirect();

        self::assertSame('draft', $loan->fresh()->status);
        self::assertSame(0, LoanBeneficiaryWriteOff::query()->where('loan_row_id', $loan->row_id)->count());
    }

    public function test_rejects_beneficiary_not_in_loan(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $orphan = $this->createMember('Orphan', '3273010203040999', 'ANG-ORPHAN');

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$orphan->row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Coba.',
                'installment_number' => 5,
            ])
            ->assertRedirect();

        self::assertSame(0, LoanBeneficiaryWriteOff::query()->where('loan_row_id', $loan->row_id)->count());
    }

    public function test_rejects_double_write_off(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $target = $loan->beneficiaries->first();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Pertama.',
                'installment_number' => 5,
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Kedua.',
                'installment_number' => 5,
            ])
            ->assertRedirect();

        self::assertSame(1, LoanBeneficiaryWriteOff::query()->where('loan_row_id', $loan->row_id)->count(), 'Hanya boleh ada 1 write-off per beneficiary.');
    }

    public function test_rejects_when_no_remaining_periods(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $target = $loan->beneficiaries->first();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Akhir.',
                'installment_number' => 10,
            ])
            ->assertRedirect();

        self::assertSame(0, LoanBeneficiaryWriteOff::query()->where('loan_row_id', $loan->row_id)->count());
    }

    public function test_rounding_drift_absorbed_by_last_period(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $target = $loan->beneficiaries->first();
        $allocated = (float) $target->allocated_amount;

        // No prior payments → write-off amount = full allocation.
        $principalBeforeModify = (float) $loan->installments
            ->where('component', 'principal')
            ->sum('principal_due');

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Meninggal.',
                'installment_number' => 5,
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $fresh = $loan->fresh(['installments']);

        // Total principal_due (principal + write_off) harus sama dengan original (rounding ±0.01).
        // Write-off row absorbs the reduction across periods 7-11 (drift goes to last row).
        $principalAfter = (float) $fresh->installments
            ->whereIn('component', ['principal', 'write_off'])
            ->sum('principal_due');

        self::assertEqualsWithDelta($principalBeforeModify, $principalAfter, 0.01);

        // No principal row should be negative (drift absorbed by last row only).
        foreach ($fresh->installments->where('component', 'principal') as $row) {
            self::assertGreaterThanOrEqual(0, (float) $row->principal_due, "Principal row #{$row->installment_number} tidak boleh negatif.");
        }

        // Sanity: reduction across remaining rows sums to write-off amount (±0.01).
        $reductionTotal = $allocated;
        $reducedSum = 0.0;
        foreach ($fresh->installments->where('component', 'principal') as $row) {
            // Rows 1-5 unchanged (600k each), rows 7-11 reduced.
            if ((int) $row->installment_number >= 7) {
                $reducedSum += (float) $row->principal_due;
            }
        }
        // Original periods 7-11 would have been 5 × 600k = 3000k. Now reduced to 3000k - 2000k = 1000k.
        self::assertEqualsWithDelta(1000.0 * 1000, $reducedSum, 0.01);
    }

    public function test_permission_required_loans_manage(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $target = $loan->beneficiaries->first();

        // Create a user assigned to 'viewer' role (which has no loans.manage).
        $viewer = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Viewer Tanpa Izin',
            'email' => 'viewer-bwo@example.test',
            'username' => 'viewer_bwo',
            'password' => 'password',
            'status' => 'active',
        ]);

        $checker = app(PermissionChecker::class);
        $checker->ensureSystemRoles();
        $checker->assignRole($viewer, 'viewer');

        $this->actingAs($viewer)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Coba tanpa izin.',
                'installment_number' => 5,
            ])
            ->assertStatus(403);

        self::assertSame(0, LoanBeneficiaryWriteOff::query()->where('loan_row_id', $loan->row_id)->count());
    }

    public function test_write_off_at_period_1_reduces_all_subsequent_periods(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $target = $loan->beneficiaries->first();
        $allocated = (float) $target->allocated_amount;

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Hapus bulan 1.',
                'installment_number' => 1,
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $fresh = $loan->fresh(['installments', 'beneficiaries']);
        $beneficiary = $fresh->beneficiaries->firstWhere('member_row_id', $target->member_row_id);
        self::assertSame($allocated, (float) $beneficiary->written_off_amount);

        // 9 remaining principal rows (2..10 → 3..11).
        $principalCount = $fresh->installments->where('component', 'principal')->count();
        self::assertSame(10, $principalCount);
        $writeOffRow = $fresh->installments->where('component', 'write_off')->first();
        self::assertSame(2, (int) $writeOffRow->installment_number);
    }

    public function test_reschedule_skips_beneficiaries_already_written_off(): void
    {
        $loan = $this->createActiveLoan(10, 6_000_000);
        $target = $loan->beneficiaries->first();

        // 1) Write-off salah satu beneficiary (sisa pokok alloc 2jt dihapusbukukan).
        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$target->member_row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Persiapan reschedule.',
                'installment_number' => 1,
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $loanAfterWriteOff = $loan->fresh(['installments', 'beneficiaries']);
        $principalRemainingBeforeReschedule = (float) $loanAfterWriteOff->installments
            ->whereIn('component', ['principal', 'write_off'])
            ->sum(fn ($i) => (float) $i->principal_due - (float) $i->principal_paid);

        // 2) Lakukan reschedule.
        $newLoan = app(LoanService::class)->reschedule($loanAfterWriteOff, [
            'rescheduled_at' => now()->toDateString(),
            'term_months' => 10,
            'service_rate_total' => 9.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
        ], (int) $this->user->row_id);

        self::assertNotNull($newLoan);
        self::assertSame('active', $newLoan->status);
        self::assertNotSame($loanAfterWriteOff->row_id, $newLoan->row_id, 'Reschedule harus bikin pinjaman baru.');

        // 3) Pinjaman baru TIDAK boleh copy beneficiary yang written-off.
        $newBeneficiaryIds = $newLoan->beneficiaries->pluck('member_row_id')->all();
        self::assertNotContains($target->member_row_id, $newBeneficiaryIds, 'Beneficiary yang di-write-off harus di-skip saat reschedule.');

        // 4) Beneficiaries di pinjaman baru = 2 (yang tidak di-write-off).
        self::assertCount(2, $newLoan->beneficiaries);

        // 5) Total alokasi pinjaman baru harus sama dengan principalRemaining loan lama
        //    (write-off sudah kurangi piutang, jadi sisa jatuhnya ke beneficiary aktif).
        $totalNewAllocated = (float) $newLoan->beneficiaries->sum('allocated_amount');
        self::assertEqualsWithDelta($principalRemainingBeforeReschedule, $totalNewAllocated, 0.01);
    }

    private function createActiveLoan(int $termMonths, float $principalAmount): Loan
    {
        $this->ensureReceivableAccounts();
        $loan = $this->createProposal($termMonths, $principalAmount);
        $this->verifiedLoan($loan);
        $this->approvedLoan($loan);

        app(LoanService::class)->disburse($loan, [
            'disbursed_at' => now()->toDateString(),
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
            'disbursement_notes' => 'Pencairan uji write-off beneficiary.',
        ], (int) $this->user->row_id);

        return $loan->fresh(['installments', 'beneficiaries', 'product', 'borrower', 'committee']);
    }

    private function ensureReceivableAccounts(): void
    {
        foreach ([
            ['code' => '1.1.03.01', 'name' => 'Piutang SPP'],
            ['code' => '1.1.04.01', 'name' => 'Cadangan Kerugian SPP'],
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

    private function createProposal(int $termMonths, float $principalAmount): Loan
    {
        return app(LoanService::class)->createProposal([
            'loan_product_id' => $this->productId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => $principalAmount,
            'service_rate_total' => 9.0,
            'term_months' => $termMonths,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->chair->row_id,
            'secretary_id' => $this->secretary->row_id,
            'treasurer_id' => $this->treasurer->row_id,
            'beneficiary_ids' => array_map(fn (Member $m) => $m->row_id, $this->beneficiaries),
        ], (int) $this->user->row_id);
    }

    private function verifiedLoan(Loan $loan): void
    {
        app(LoanService::class)->verify($loan, [
            'verified_at' => '2026-07-20',
            'verification_amount' => $loan->principal_amount,
            'verification_notes' => 'Verifikasi OK.',
        ], (int) $this->user->row_id);
    }

    private function approvedLoan(Loan $loan): void
    {
        $beneficiaries = $loan->beneficiaries->map(fn (LoanBeneficiary $b): array => [
            'member_row_id' => $b->member_row_id,
            'allocated_amount' => round(((float) $loan->principal_amount) / $loan->beneficiaries->count(), 2),
        ])->all();

        app(LoanService::class)->approve($loan, [
            'approved_at' => '2026-07-21',
            'planned_disbursed_at' => '2026-07-25',
            'allocated_principal' => $loan->principal_amount,
            'beneficiaries' => $beneficiaries,
        ], (int) $this->user->row_id);
    }

    private function recordPartialPrincipalPayments(Loan $loan, int $memberRowId, int $periods, float $perPeriodAmount): void
    {
        $now = now();
        for ($i = 1; $i <= $periods; $i++) {
            LoanInstallmentTracking::query()->create([
                'loan_row_id' => $loan->row_id,
                'installment_number' => $i,
                'member_row_id' => $memberRowId,
                'principal_paid' => $perPeriodAmount,
                'interest_paid' => 0,
                'penalty_paid' => 0,
                'journal_entry_row_id' => null,
                'recorded_at' => $now,
            ]);
        }
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
            'organization_unit_row_id' => 1,
            'member_number' => $memberNumber,
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
    }
}
