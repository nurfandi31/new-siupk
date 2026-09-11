<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanStatusHistory;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
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

final class LoanVerificationAndApprovalFormTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private array $committee = [];

    private array $beneficiaries = [];

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Parameter',
            'email' => 'loan-parameters@example.test',
            'username' => 'loan_parameters_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Parameter',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-PARAM',
            'name' => 'Kelompok Parameter',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $this->committee = [
            $this->createMember('Ketua Parameter', '3273010203040201', 'PARAM-KET'),
            $this->createMember('Sekretaris Parameter', '3273010203040202', 'PARAM-SEK'),
            $this->createMember('Bendahara Parameter', '3273010203040203', 'PARAM-BEN'),
        ];
        $this->beneficiaries = [
            $this->createMember('Anggota Parameter 1', '3273010203040204', 'PARAM-A1'),
            $this->createMember('Anggota Parameter 2', '3273010203040205', 'PARAM-A2'),
        ];

        foreach ([
            ['chair', $this->committee[0]],
            ['secretary', $this->committee[1]],
            ['treasurer', $this->committee[2]],
        ] as [$position, $member]) {
            GroupOfficer::query()->create([
                'group_row_id' => $this->group->row_id,
                'member_row_id' => $member->row_id,
                'position' => $position,
                'started_at' => '2026-01-01',
            ]);
        }

        foreach ([...$this->committee, ...$this->beneficiaries] as $member) {
            GroupMember::query()->create([
                'group_row_id' => $this->group->row_id,
                'member_row_id' => $member->row_id,
                'joined_at' => '2026-01-01',
                'status' => 'active',
            ]);
        }

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_verification_form_saves_recommended_schedule_parameters_to_history(): void
    {
        $loan = $this->createProposal();

        $this->actingAs($this->user)
            ->patch("/lending/loans/{$loan->row_id}/verify", [
                'verified_at' => '2026-09-08',
                'verification_amount' => 6_000_000,
                'verification_notes' => 'Hasil verifikasi dan penyesuaian parameter.',
                'term_months' => 12,
                'service_rate_total' => 12,
                'principal_frequency' => 'bimonthly',
                'interest_frequency' => 'quarterly',
                'principal_grace_months' => 2,
                'interest_grace_months' => 3,
            ])
            ->assertRedirect("/lending/loans/{$loan->row_id}");

        $history = $this->history($loan, 'verified');

        self::assertSame(12, (int) $history->term_months);
        self::assertSame(12.0, (float) $history->service_rate_total);
        self::assertSame('bimonthly', (string) $history->principal_frequency);
        self::assertSame('quarterly', (string) $history->interest_frequency);
        self::assertSame(2, (int) $history->principal_grace_months);
        self::assertSame(3, (int) $history->interest_grace_months);
    }

    public function test_allocation_form_updates_final_parameters_and_regenerates_schedule(): void
    {
        $loan = $this->createProposal();

        $this->actingAs($this->user)
            ->patch("/lending/loans/{$loan->row_id}/verify", [
                'verified_at' => '2026-09-08',
                'verification_amount' => 6_000_000,
                'verification_notes' => 'Verifikasi selesai.',
                'term_months' => 12,
                'service_rate_total' => 12,
                'principal_frequency' => 'monthly',
                'interest_frequency' => 'monthly',
            ])
            ->assertRedirect("/lending/loans/{$loan->row_id}");

        $this->actingAs($this->user)
            ->patch("/lending/loans/{$loan->row_id}/approve", [
                'approved_at' => '2026-09-08',
                'planned_disbursed_at' => '2026-09-10',
                'allocated_principal' => 6_000_000,
                'allocation_notes' => 'Alokasi dengan parameter final.',
                'beneficiaries' => array_map(fn (Member $member): array => [
                    'member_row_id' => $member->row_id,
                    'allocated_amount' => 3_000_000,
                ], $this->beneficiaries),
                'term_months' => 12,
                'service_rate_total' => 12,
                'principal_frequency' => 'bimonthly',
                'interest_frequency' => 'quarterly',
                'principal_grace_months' => 2,
                'interest_grace_months' => 3,
            ])
            ->assertRedirect("/lending/loans/{$loan->row_id}");

        $fresh = Loan::query()->findOrFail($loan->row_id);
        $allocation = $this->history($loan, 'waiting');
        $principalRows = $fresh->installments()
            ->where('component', 'principal')
            ->orderBy('installment_number')
            ->get();
        $interestRows = $fresh->installments()
            ->where('component', 'interest')
            ->orderBy('installment_number')
            ->get();

        self::assertSame(12, (int) $fresh->term_months);
        self::assertSame(12.0, (float) $fresh->service_rate_total);
        self::assertSame('bimonthly', (string) $fresh->principal_frequency);
        self::assertSame('quarterly', (string) $fresh->interest_frequency);
        self::assertSame(2, (int) $fresh->principal_grace_months);
        self::assertSame(3, (int) $fresh->interest_grace_months);

        self::assertSame(12, (int) $allocation->term_months);
        self::assertSame(12.0, (float) $allocation->service_rate_total);
        self::assertSame('bimonthly', (string) $allocation->principal_frequency);
        self::assertSame('quarterly', (string) $allocation->interest_frequency);
        self::assertSame(2, (int) $allocation->principal_grace_months);
        self::assertSame(3, (int) $allocation->interest_grace_months);

        self::assertSame(
            ['2027-03-08', '2027-05-08', '2027-07-08', '2027-09-08', '2027-11-08'],
            $principalRows->pluck('due_date')->map(fn ($date) => $date->format('Y-m-d'))->all()
        );
        self::assertSame([1_200_000.0, 1_200_000.0, 1_200_000.0, 1_200_000.0, 1_200_000.0], $principalRows->pluck('principal_due')->map(fn ($amount) => (float) $amount)->all());
        self::assertSame(
            ['2027-09-08', '2027-12-08', '2028-03-08'],
            $interestRows->pluck('due_date')->map(fn ($date) => $date->format('Y-m-d'))->all()
        );
        self::assertSame([240_000.0, 240_000.0, 240_000.0], $interestRows->pluck('interest_due')->map(fn ($amount) => (float) $amount)->all());
    }

    private function createProposal(): Loan
    {
        return app(LoanService::class)->createProposal([
            'loan_product_id' => $this->productId,
            'group_id' => $this->group->row_id,
            'proposed_at' => '2026-09-08',
            'principal_amount' => 6_000_000,
            'service_rate_total' => 9,
            'term_months' => 6,
            'installment_method' => 'flat',
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'chair_id' => $this->committee[0]->row_id,
            'secretary_id' => $this->committee[1]->row_id,
            'treasurer_id' => $this->committee[2]->row_id,
            'beneficiary_ids' => array_map(fn (Member $member) => $member->row_id, $this->beneficiaries),
        ], (int) $this->user->row_id);
    }

    private function history(Loan $loan, string $status): LoanStatusHistory
    {
        $history = LoanStatusHistory::query()
            ->where('loan_row_id', $loan->row_id)
            ->where('to_status', $status)
            ->latest('changed_at')
            ->first();

        self::assertNotNull($history, "Missing {$status} history.");

        return $history;
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
