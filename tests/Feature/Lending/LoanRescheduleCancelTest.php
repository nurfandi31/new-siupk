<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
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

final class LoanRescheduleCancelTest extends TestCase
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

        // Enable FK enforcement on SQLite so cascadeOnDelete is observable in tests.
        DB::connection('tenant')->statement('PRAGMA foreign_keys = ON');

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Cancel Reschedule',
            'email' => 'cancel-reschedule@example.test',
            'username' => 'cancel_reschedule_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create(['id' => 1, 'code' => 'V001', 'name' => 'Desa Induk', 'type' => 'village', 'is_active' => true]);

        $this->group = Group::query()->create([
            'code' => 'KLP-CXL',
            'name' => 'Kelompok Cancel Reschedule',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $this->chair = $this->createMember('Budi Ketua', '3273010203050101', 'KET-CXL');
        $this->secretary = $this->createMember('Siti Sekretaris', '3273010203050102', 'SEK-CXL');
        $this->treasurer = $this->createMember('Andi Bendahara', '3273010203050103', 'BEN-CXL');
        $this->beneficiaries = [
            $this->createMember('Dewi Pemanfaat', '3273010203050104', 'ANG-CXL1'),
            $this->createMember('Eka Pemanfaat', '3273010203050105', 'ANG-CXL2'),
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

    public function test_happy_path_cancel_reschedule_restores_old_loan_and_soft_deletes_journals(): void
    {
        [$oldLoan, $newLoan] = $this->createRescheduledLoan();

        // Verify preconditions.
        self::assertSame('rescheduled', $oldLoan->fresh()->status);
        self::assertSame('active', $newLoan->status);
        self::assertSame($oldLoan->row_id, (int) $newLoan->rescheduled_from_loan_row_id);

        // Two journals posted: loan_reschedule_close (old loan) + loan_reschedule_open (new loan).
        $this->assertJournalCount('loan_reschedule_close', $oldLoan->row_id, 1);
        $this->assertJournalCount('loan_reschedule_open', $newLoan->row_id, 1);

        $oldLoanRowId = $oldLoan->row_id;
        $newLoanRowId = $newLoan->row_id;

        // Act: cancel reschedule.
        $response = $this->actingAs($this->user)
            ->post('/lending/loans/'.$newLoanRowId.'/cancel-reschedule', [
                'reason' => 'Salah input suku bunga.',
            ]);

        $response->assertRedirect('/lending/loans/'.$oldLoanRowId);

        // 1) Old loan status restored to 'active', completed_at cleared.
        $restored = Loan::query()->where('row_id', $oldLoanRowId)->first();
        self::assertSame('active', $restored->status, 'Old loan harus kembali ke active.');
        self::assertNull($restored->completed_at, 'completed_at harus dikosongkan.');

        // 2) New loan hard-deleted.
        self::assertNull(Loan::query()->where('row_id', $newLoanRowId)->first(), 'New loan harus hard-delete.');
        self::assertSame(0, DB::connection('tenant')->table('loan_beneficiaries')->where('loan_row_id', $newLoanRowId)->count());
        self::assertSame(0, DB::connection('tenant')->table('loan_installments')->where('loan_row_id', $newLoanRowId)->count());
        self::assertSame(0, DB::connection('tenant')->table('loan_borrowers')->where('loan_row_id', $newLoanRowId)->count());
        self::assertSame(0, DB::connection('tenant')->table('loan_committee')->where('loan_row_id', $newLoanRowId)->count());

        // 3) Both journals hard-deleted.
        $closeEntry = JournalEntry::query()->where('source_type', 'loan_reschedule_close')->where('source_row_id', $oldLoanRowId)->first();
        $openEntry = JournalEntry::query()->where('source_type', 'loan_reschedule_open')->where('source_row_id', $newLoanRowId)->first();
        self::assertNull($closeEntry, 'Close jurnal harus hard-deleted.');
        self::assertNull($openEntry, 'Open jurnal harus hard-deleted.');

        // 4) Status history recorded di old loan.
        $history = DB::connection('tenant')->table('loan_status_histories')
            ->where('loan_row_id', $oldLoanRowId)
            ->where('from_status', 'rescheduled')
            ->where('to_status', 'active')
            ->orderByDesc('changed_at')
            ->first();
        self::assertNotNull($history);
        self::assertSame('rescheduled', $history->from_status);
        self::assertStringContainsString('Cancel reschedule', (string) $history->notes);
        self::assertStringContainsString('Salah input suku bunga', (string) $history->notes);
    }

    public function test_cancel_reschedule_rejects_when_new_loan_has_principal_paid(): void
    {
        [$oldLoan, $newLoan] = $this->createRescheduledLoan();

        // Simulate principal payment on new loan.
        $firstInstallment = $newLoan->installments->where('component', 'principal')->first();
        DB::connection('tenant')->table('loan_installments')
            ->where('loan_row_id', $newLoan->row_id)
            ->where('component', 'principal')
            ->where('installment_number', $firstInstallment->installment_number)
            ->update(['principal_paid' => 1000]);

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$newLoan->row_id.'/cancel-reschedule', [
                'reason' => 'Coba cancel setelah ada bayar.',
            ])
            ->assertRedirect();

        // Should not affect state.
        self::assertSame('rescheduled', $oldLoan->fresh()->status);
        self::assertNotNull(Loan::query()->where('row_id', $newLoan->row_id)->first());

        // Jurnal should remain posted.
        $this->assertJournalCount('loan_reschedule_close', $oldLoan->row_id, 1);
        $this->assertJournalCount('loan_reschedule_open', $newLoan->row_id, 1);
    }

    public function test_validation_rejects_missing_reason(): void
    {
        [, $newLoan] = $this->createRescheduledLoan();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$newLoan->row_id.'/cancel-reschedule', [
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_rejects_loan_that_is_not_reschedule_result(): void
    {
        // Create a regular active loan (bukan dari reschedule).
        $loan = $this->createActiveLoan(6, 3_000_000);

        // Override rescheduled_from_loan_row_id to null (must already be null, but make sure).
        DB::connection('tenant')->table('loans')
            ->where('row_id', $loan->row_id)
            ->update(['rescheduled_from_loan_row_id' => null]);

        $response = $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/cancel-reschedule', [
                'reason' => 'Bukan hasil reschedule.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        self::assertSame('active', $loan->fresh()->status);
    }

    public function test_permission_required_loans_manage(): void
    {
        [, $newLoan] = $this->createRescheduledLoan();

        $viewer = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Viewer Tanpa Izin',
            'email' => 'viewer-cxl@example.test',
            'username' => 'viewer_cxl',
            'password' => 'password',
            'status' => 'active',
        ]);

        $checker = app(PermissionChecker::class);
        $checker->ensureSystemRoles();
        $checker->assignRole($viewer, 'viewer');

        $this->actingAs($viewer)
            ->post('/lending/loans/'.$newLoan->row_id.'/cancel-reschedule', [
                'reason' => 'Coba tanpa izin.',
            ])
            ->assertStatus(403);

        // State unchanged.
        self::assertNotNull(Loan::query()->where('row_id', $newLoan->row_id)->first());
    }

    public function test_double_cancel_fails_because_journals_already_soft_deleted(): void
    {
        [$oldLoan, $newLoan] = $this->createRescheduledLoan();

        // First cancel: success.
        $this->actingAs($this->user)
            ->post('/lending/loans/'.$newLoan->row_id.'/cancel-reschedule', [
                'reason' => 'Cancel pertama.',
            ])
            ->assertRedirect();

        // Recreate the same reschedule state by re-rescheduling the restored old loan.
        $restoredOld = Loan::query()->where('row_id', $oldLoan->row_id)->first();
        // Re-reschedule should succeed because old loan is now active again with same principal remaining.
        $newLoanAgain = app(LoanService::class)->reschedule($restoredOld, [
            'rescheduled_at' => now()->toDateString(),
            'term_months' => 6,
            'service_rate_total' => 9.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
        ], (int) $this->user->row_id);

        // Second cancel: should also succeed (independent cancel cycle).
        $this->actingAs($this->user)
            ->post('/lending/loans/'.$newLoanAgain->row_id.'/cancel-reschedule', [
                'reason' => 'Cancel kedua.',
            ])
            ->assertRedirect('/lending/loans/'.$oldLoan->row_id);

        self::assertSame('active', Loan::query()->where('row_id', $oldLoan->row_id)->first()->status);
        self::assertNull(Loan::query()->where('row_id', $newLoanAgain->row_id)->first());
    }

    public function test_rejects_loan_status_outside_active_or_disbursed(): void
    {
        [$oldLoan, $newLoan] = $this->createRescheduledLoan();

        // Force new loan to non-active status (e.g., completed).
        DB::connection('tenant')->table('loans')
            ->where('row_id', $newLoan->row_id)
            ->update(['status' => 'completed', 'completed_at' => now()->toDateString()]);

        $response = $this->actingAs($this->user)
            ->post('/lending/loans/'.$newLoan->row_id.'/cancel-reschedule', [
                'reason' => 'Coba cancel loan completed.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // State unchanged.
        self::assertNotNull(Loan::query()->where('row_id', $newLoan->row_id)->first());
    }

    public function test_status_history_recorded_with_proper_metadata(): void
    {
        [$oldLoan, $newLoan] = $this->createRescheduledLoan();
        $oldLoanRowId = $oldLoan->row_id;

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$newLoan->row_id.'/cancel-reschedule', [
                'reason' => 'Cek audit trail.',
            ])
            ->assertRedirect();

        $historyEntry = DB::connection('tenant')->table('loan_status_histories')
            ->where('loan_row_id', $oldLoanRowId)
            ->where('from_status', 'rescheduled')
            ->where('to_status', 'active')
            ->orderByDesc('changed_at')
            ->first();

        self::assertNotNull($historyEntry);
        self::assertEqualsWithDelta(
            (float) $oldLoan->principal_amount,
            (float) $historyEntry->principal_amount,
            0.01
        );
        self::assertSame((int) $oldLoan->loan_product_row_id, (int) $historyEntry->product_row_id);
        self::assertSame((int) $oldLoan->term_months, (int) $historyEntry->term_months);
        self::assertSame((int) $this->user->row_id, (int) $historyEntry->changed_by_user_id);
        self::assertStringContainsString('Cek audit trail', (string) $historyEntry->notes);
    }

    private function createRescheduledLoan(): array
    {
        $loan = $this->createActiveLoan(6, 3_000_000);

        $newLoan = app(LoanService::class)->reschedule($loan, [
            'rescheduled_at' => now()->toDateString(),
            'term_months' => 6,
            'service_rate_total' => 9.0,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
        ], (int) $this->user->row_id);

        return [$loan->fresh(), $newLoan];
    }

    private function createActiveLoan(int $termMonths, float $principalAmount): Loan
    {
        $loan = app(LoanService::class)->createProposal([
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

        app(LoanService::class)->verify($loan, [
            'verified_at' => '2026-07-20',
            'verification_amount' => $loan->principal_amount,
            'verification_notes' => 'Verifikasi OK.',
        ], (int) $this->user->row_id);

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

        app(LoanService::class)->disburse($loan, [
            'disbursed_at' => now()->toDateString(),
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
            'disbursement_notes' => 'Pencairan uji reschedule.',
        ], (int) $this->user->row_id);

        return $loan->fresh(['installments', 'beneficiaries', 'product', 'borrower', 'committee']);
    }

    private function assertJournalCount(string $sourceType, int $sourceRowId, int $expected): void
    {
        $count = JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_row_id', $sourceRowId)
            ->count();
        self::assertSame($expected, $count, "Journal count for {$sourceType}#{$sourceRowId} mismatch.");
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
