<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Models\Account;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanStatusHistory;
use App\Domain\Lending\Models\LoanWriteOff;
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

final class LoanLifecycleTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private Member $chair;

    private Member $secretary;

    private Member $treasurer;

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
            'name' => 'Petugas Lifecycle',
            'email' => 'lifecycle@example.test',
            'username' => 'lifecycle_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create(['id' => 1, 'code' => 'V001', 'name' => 'Desa Induk', 'type' => 'village', 'is_active' => true]);

        $this->group = Group::query()->create([
            'code' => 'KLP-LIFECYCLE',
            'name' => 'Kelompok Siklus',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $this->chair = $this->createMember('Budi Ketua', '3273010203040001', 'KET-LC');
        $this->secretary = $this->createMember('Siti Sekretaris', '3273010203040002', 'SEK-LC');
        $this->treasurer = $this->createMember('Andi Bendahara', '3273010203040003', 'BEN-LC');
        $this->beneficiaries = [
            $this->createMember('Dewi Pemanfaat', '3273010203040004', 'ANG-LC1'),
            $this->createMember('Eka Pemanfaat', '3273010203040005', 'ANG-LC2'),
            $this->createMember('Fajar Pemanfaat', '3273010203040006', 'ANG-LC3'),
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

    public function test_show_renders_detail_with_status_specific_sections(): void
    {
        $loan = $this->createProposal();

        $this->actingAs($this->user)
            ->get('/lending/loans/'.$loan->row_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Show')
                ->where('loan.status', 'draft')
                ->where('loan.row_id', $loan->row_id)
                ->has('loan.principal_amount')
                ->has('loan.committee')
                ->has('loan.beneficiaries', 3)
                ->has('loan.installments')
                ->has('loan.status_histories')
                ->has('disbursementAccounts')
            );
    }

    public function test_verify_transitions_draft_to_verified_and_persists_history(): void
    {
        $loan = $this->createProposal();

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/verify', [
                'verified_at' => '2026-07-20',
                'verification_amount' => 6000000,
                'verification_notes' => 'Dokumen lengkap, verifikasi lapangan sesuai.',
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $fresh = Loan::query()->findOrFail($loan->row_id);
        self::assertSame('verified', $fresh->status);
        self::assertSame('2026-07-20', $fresh->verified_at->format('Y-m-d'));
        self::assertSame(6000000.0, (float) $fresh->fresh()->principal_amount);

        $history = LoanStatusHistory::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('to_status', 'verified')
            ->first();
        self::assertNotNull($history);
        self::assertSame('draft', $history->from_status);
        self::assertSame(6000000.0, (float) $history->principal_amount);
    }

    public function test_verify_validation_rejects_missing_date_only(): void
    {
        $loan = $this->createProposal();

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/verify', [
                'verified_at' => '',
                'verification_notes' => '',
            ])
            ->assertSessionHasErrors(['verified_at'])
            ->assertSessionDoesntHaveErrors(['verification_notes']);

        self::assertSame('draft', $loan->fresh()->status);
    }

    public function test_verify_accepts_blank_notes(): void
    {
        $loan = $this->createProposal();

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/verify', [
                'verified_at' => '2026-07-20',
                'verification_notes' => '',
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        self::assertSame('verified', $loan->fresh()->status);
    }

    public function test_approve_transitions_verified_to_waiting_and_updates_beneficiary_allocations(): void
    {
        $loan = $this->createProposal();
        $this->verifiedLoan($loan);

        $allocations = [
            ['member_row_id' => $this->beneficiaries[0]->row_id, 'allocated_amount' => 2500000],
            ['member_row_id' => $this->beneficiaries[1]->row_id, 'allocated_amount' => 2000000],
            ['member_row_id' => $this->beneficiaries[2]->row_id, 'allocated_amount' => 1500000],
        ];

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/approve', [
                'approved_at' => '2026-07-21',
                'planned_disbursed_at' => '2026-07-25',
                'allocated_principal' => 6000000,
                'beneficiaries' => $allocations,
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $fresh = Loan::query()->findOrFail($loan->row_id);
        self::assertSame('waiting', $fresh->status);
        self::assertSame('2026-07-21', $fresh->approved_at->format('Y-m-d'));
        self::assertSame('2026-07-25', $fresh->funded_at->format('Y-m-d'));
        self::assertSame(6000000.0, (float) $fresh->principal_amount);

        $beneficiaries = LoanBeneficiary::query()->where('loan_row_id', $loan->row_id)->get()->keyBy('member_row_id');
        self::assertSame(2500000.0, (float) $beneficiaries[$this->beneficiaries[0]->row_id]->allocated_amount);
        self::assertSame(2000000.0, (float) $beneficiaries[$this->beneficiaries[1]->row_id]->allocated_amount);
        self::assertSame(1500000.0, (float) $beneficiaries[$this->beneficiaries[2]->row_id]->allocated_amount);
    }

    public function test_approve_validation_rejects_missing_dates_and_beneficiaries(): void
    {
        $loan = $this->createProposal();
        $this->verifiedLoan($loan);

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/approve', [
                'approved_at' => '',
                'planned_disbursed_at' => '',
                'allocated_principal' => '',
                'beneficiaries' => [],
            ])
            ->assertSessionHasErrors(['approved_at', 'planned_disbursed_at', 'allocated_principal', 'beneficiaries']);

        self::assertSame('verified', $loan->fresh()->status);
    }

    public function test_disburse_transitions_waiting_to_active_and_records_source_account(): void
    {
        $loan = $this->createProposal();
        $this->verifiedLoan($loan);
        $this->approvedLoan($loan);

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/disburse', [
                'disbursed_at' => now()->toDateString(),
                'disbursement_account_row_id' => $this->disbursementAccount->row_id,
                'disbursement_notes' => 'Pencairan via kas utama.',
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $fresh = Loan::query()->findOrFail($loan->row_id);
        self::assertSame('active', $fresh->status);
        self::assertSame(now()->toDateString(), $fresh->disbursed_at->format('Y-m-d'));
        self::assertSame($this->disbursementAccount->row_id, $fresh->disbursement_account_row_id);
        self::assertSame('Pencairan via kas utama.', $fresh->disbursement_notes);
    }

    public function test_disburse_validation_rejects_unknown_account(): void
    {
        $loan = $this->createProposal();
        $this->verifiedLoan($loan);
        $this->approvedLoan($loan);

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/disburse', [
                'disbursed_at' => '2026-07-26',
                'disbursement_account_row_id' => 99999999,
                'disbursement_notes' => null,
            ])
            ->assertSessionHasErrors('disbursement_account_row_id');

        self::assertSame('waiting', $loan->fresh()->status);
    }

    public function test_show_requires_authentication(): void
    {
        $loan = $this->createProposal();

        auth()->guard('web')->forgetUser();
        $this->app['auth']->forgetGuards();

        $response = $this->get('/lending/loans/'.$loan->row_id);
        $response->assertStatus(302);

        self::assertSame('draft', $loan->fresh()->status);
    }

    public function test_update_modifies_proposal_principal_and_beneficiaries_in_draft(): void
    {
        $loan = $this->createProposal();
        $first = LoanBeneficiary::query()->where('loan_row_id', $loan->row_id)->firstOrFail();

        $payload = [
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6000000,
            'service_rate_total' => 12,
            'term_months' => 12,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'beneficiary_amounts' => [
                $first->member_row_id => 6000000,
            ],
        ];

        $this->actingAs($this->user)
            ->put('/lending/loans/'.$loan->row_id, $payload)
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $loan->refresh();
        self::assertSame(6000000.0, (float) $loan->principal_amount);
        self::assertSame(12, (int) $loan->term_months);
        self::assertSame(6000000.0, (float) LoanBeneficiary::query()->where('loan_row_id', $loan->row_id)->where('member_row_id', $first->member_row_id)->value('allocated_amount'));
    }

    public function test_update_rejects_when_loan_already_in_waiting(): void
    {
        $loan = $this->createProposal();
        app(LoanService::class)->verify($loan, ['verified_at' => '2026-07-21', 'verification_amount' => null, 'verification_notes' => 'Cukup'], (int) $this->user->row_id);
        app(LoanService::class)->approve($loan, [
            'approved_at' => '2026-07-21',
            'planned_disbursed_at' => '2026-07-30',
            'allocated_principal' => 6000000,
            'beneficiaries' => [['member_row_id' => $loan->beneficiaries()->value('member_row_id'), 'allocated_amount' => 4500000]],
        ], (int) $this->user->row_id);

        $payload = [
            'proposed_at' => '2026-07-20',
            'principal_amount' => 9999,
            'service_rate_total' => 1,
            'term_months' => 1,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'beneficiary_amounts' => [],
        ];

        $this->actingAs($this->user)
            ->put('/lending/loans/'.$loan->row_id, $payload)
            ->assertRedirect();

        self::assertNotSame(9999.0, (float) $loan->fresh()->principal_amount);
    }

    public function test_create_proposal_persists_proposed_amount_per_beneficiary(): void
    {
        $loan = $this->createProposal();
        $beneficiaries = $loan->beneficiaries()->orderBy('member_row_id')->get();

        self::assertSame(3, $beneficiaries->count());
        self::assertNotNull($beneficiaries->first()->proposed_amount);
        self::assertSame((float) $beneficiaries->first()->proposed_amount, (float) $beneficiaries->first()->allocated_amount);
    }

    public function test_verify_records_per_beneficiary_verified_amounts(): void
    {
        $loan = $this->createProposal();
        $members = $loan->beneficiaries()->pluck('member_row_id')->all();

        $payload = [
            'verified_at' => '2026-07-21',
            'verification_amount' => null,
            'verification_notes' => 'Cukup sesuai usulan.',
            'verified_amounts' => [
                $members[0] => 2500000,
                $members[1] => 2000000,
                $members[2] => 1500000,
            ],
        ];

        $this->actingAs($this->user)
            ->patch('/lending/loans/'.$loan->row_id.'/verify', $payload)
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $byMember = $loan->fresh()->beneficiaries->keyBy('member_row_id');
        self::assertSame(2500000.0, (float) $byMember[$members[0]]->verified_amount);
        self::assertSame(2000000.0, (float) $byMember[$members[1]]->verified_amount);
        self::assertSame(1500000.0, (float) $byMember[$members[2]]->verified_amount);
    }

    public function test_remove_beneficiary_deletes_beneficiary_in_draft(): void
    {
        $loan = $this->createProposal();
        $first = $loan->beneficiaries()->firstOrFail();

        $this->actingAs($this->user)
            ->delete('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$first->member_row_id)
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        self::assertSame(2, $loan->fresh()->beneficiaries()->count());
        self::assertNull($loan->fresh()->beneficiaries()->where('member_row_id', $first->member_row_id)->first());
    }

    public function test_remove_beneficiary_rejects_when_loan_already_waiting(): void
    {
        $loan = $this->createProposal();
        app(LoanService::class)->verify($loan, ['verified_at' => '2026-07-21', 'verification_amount' => null, 'verification_notes' => 'Cukup'], (int) $this->user->row_id);
        app(LoanService::class)->approve($loan, [
            'approved_at' => '2026-07-21',
            'planned_disbursed_at' => '2026-07-30',
            'allocated_principal' => 6000000,
            'beneficiaries' => [['member_row_id' => $loan->beneficiaries()->value('member_row_id'), 'allocated_amount' => 4500000]],
        ], (int) $this->user->row_id);

        $first = $loan->fresh()->beneficiaries()->firstOrFail();

        $this->actingAs($this->user)
            ->delete('/lending/loans/'.$loan->row_id.'/beneficiaries/'.$first->member_row_id)
            ->assertRedirect();

        self::assertSame(3, $loan->fresh()->beneficiaries()->count());
    }

    public function test_write_off_closes_active_loan_and_records_write_off(): void
    {
        $loan = $this->createActiveLoan();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/write-off', [
                'written_off_at' => now()->toDateString(),
                'reason' => 'Musyawarah pengurus, debitur meninggal.',
            ])
            ->assertRedirect('/lending/loans/'.$loan->row_id);

        $fresh = $loan->fresh();
        self::assertSame('written_off', $fresh->status);
        self::assertSame(now()->toDateString(), $fresh->completed_at?->format('Y-m-d'));

        $writeOff = LoanWriteOff::query()->where('loan_row_id', $loan->row_id)->first();
        self::assertNotNull($writeOff);
        self::assertGreaterThan(0, (float) $writeOff->principal_balance);
        self::assertSame('Musyawarah pengurus, debitur meninggal.', $writeOff->reason);

        $history = LoanStatusHistory::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('to_status', 'written_off')
            ->first();
        self::assertNotNull($history);
        self::assertSame('active', $history->from_status);
    }

    public function test_write_off_requires_reason_and_date(): void
    {
        $loan = $this->createActiveLoan();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/write-off', [
                'written_off_at' => '',
                'reason' => '',
            ])
            ->assertSessionHasErrors(['written_off_at', 'reason']);

        self::assertSame('active', $loan->fresh()->status);
    }

    public function test_reschedule_closes_old_loan_and_creates_active_successor(): void
    {
        $loan = $this->createActiveLoan();
        $oldPrincipal = (float) $loan->principal_amount;

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/reschedule', [
                'rescheduled_at' => now()->toDateString(),
                'term_months' => 12,
                'service_rate_total' => 10,
                'installment_method' => 'flat',
                'principal_frequency' => 'monthly',
                'interest_frequency' => 'monthly',
            ])
            ->assertRedirect();

        self::assertSame('rescheduled', $loan->fresh()->status);

        $newLoan = Loan::query()
            ->where('status', 'active')
            ->where('row_id', '!=', $loan->row_id)
            ->orderByDesc('row_id')
            ->first();
        self::assertNotNull($newLoan);
        self::assertSame($oldPrincipal, (float) $newLoan->principal_amount);
        self::assertSame(12, (int) $newLoan->term_months);
        self::assertSame(10.0, (float) $newLoan->service_rate_total);
        self::assertSame($loan->loan_product_row_id, $newLoan->loan_product_row_id);
        self::assertGreaterThan(0, $newLoan->installments()->count());
        self::assertSame($loan->beneficiaries()->count(), $newLoan->beneficiaries()->count());
    }

    public function test_reschedule_rejects_non_active_loan(): void
    {
        $loan = $this->createProposal();

        $this->actingAs($this->user)
            ->post('/lending/loans/'.$loan->row_id.'/reschedule', [
                'rescheduled_at' => now()->toDateString(),
                'term_months' => 12,
                'service_rate_total' => 10,
                'installment_method' => 'flat',
                'principal_frequency' => 'monthly',
                'interest_frequency' => 'monthly',
            ])
            ->assertRedirect();

        self::assertSame('draft', $loan->fresh()->status);
        self::assertSame(1, Loan::query()->count());
    }

    private function createActiveLoan(): Loan
    {
        $this->ensureReceivableAccounts();

        $loan = $this->createProposal();
        $this->verifiedLoan($loan);
        $this->approvedLoan($loan);

        app(LoanService::class)->disburse($loan, [
            'disbursed_at' => now()->toDateString(),
            'disbursement_account_row_id' => $this->disbursementAccount->row_id,
            'disbursement_notes' => 'Pencairan uji.',
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

    private function createProposal(): Loan
    {
        return app(LoanService::class)->createProposal([
            'loan_product_id' => $this->productId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-07-20',
            'principal_amount' => 6000000,
            'service_rate_total' => 9.0,
            'term_months' => 6,
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
            'allocated_principal' => 6000000,
            'beneficiaries' => $beneficiaries,
        ], (int) $this->user->row_id);
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
