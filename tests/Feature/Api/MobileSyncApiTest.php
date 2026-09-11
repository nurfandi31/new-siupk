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
use App\Models\Scopes\VillageScope;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\FiscalPeriodProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MobileSyncApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $collector;

    private Loan $loan;

    private Member $member;

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
            'name' => 'Kolektor Sinkron',
            'username' => 'kolektor_sync',
            'email' => 'kolektor.sync@example.com',
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

        $this->token = $this->collector->createToken('Flutter Sync')->plainTextToken;
        $this->loan = $this->createLoan(villageId: 1, loanId: 1, loanNumber: 'SPP-2026-001');
        $this->createLoan(villageId: 2, loanId: 2, loanNumber: 'SPP-2026-002');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_village_user_pull_is_scoped_to_assigned_village(): void
    {
        $this->collector->forceFill([
            'is_village_user' => true,
            'village_row_id' => 1,
        ])->save();

        $response = $this->pull();

        $response->assertOk();
        $loanNumbers = collect($response->json('data.loans'))->pluck('loan_number');
        $this->assertContains('SPP-2026-001', $loanNumbers);
        $this->assertNotContains('SPP-2026-002', $loanNumbers);
    }

    public function test_full_tenant_user_pull_contains_all_loans(): void
    {
        $response = $this->pull();

        $response->assertOk();
        $loanNumbers = collect($response->json('data.loans'))->pluck('loan_number');
        $this->assertContains('SPP-2026-001', $loanNumbers);
        $this->assertContains('SPP-2026-002', $loanNumbers);
    }

    public function test_pull_since_filters_updated_loans(): void
    {
        $response = $this->pull();
        $syncedAt = (string) $response->json('data.generated_at');

        DB::connection('tenant')->table('loans')->where('id', 2)->update([
            'updated_at' => now()->addSecond(),
        ]);

        $response = $this->pull(['since' => $syncedAt]);

        $loanNumbers = collect($response->json('data.loans'))->pluck('loan_number');
        $this->assertContains('SPP-2026-002', $loanNumbers);
        $this->assertNotContains('SPP-2026-001', $loanNumbers);
        $this->assertNotEmpty($response->json('data.generated_at'));
    }

    public function test_push_loan_payment_is_accepted_and_idempotent(): void
    {
        $cashAccount = Account::on('tenant')->where('is_postable', true)->where('code', 'like', '1.1.01.%')->first();
        $mutationUuid = (string) Str::uuid();
        $mutation = $this->paymentMutation($mutationUuid, (int) $cashAccount->row_id);

        $this->push([$mutation])->assertOk()
            ->assertJsonPath('data.accepted.0', $mutationUuid);

        $this->assertSame(1, DB::connection('tenant')->table('loan_payments')->where('reference_number', $mutationUuid)->count());
        $this->assertSame(0, DB::connection('tenant')->table('sync_mutations')->where('mutation_uuid', $mutationUuid)->count());
        $this->assertSame(1, DB::connection('tenant')->table('journal_entries')->where('source_type', 'loan_installment')->count());
    }

    public function test_push_verification_conflicts_when_server_status_changed(): void
    {
        $this->loan->forceFill(['status' => 'approved', 'updated_at' => now()->addMinute()])->save();

        $mutationUuid = (string) Str::uuid();
        $response = $this->push([$this->verificationMutation($mutationUuid)]);

        $response->assertOk()->assertJsonPath('data.conflicts.0.mutation_uuid', $mutationUuid);
        $this->assertDatabaseHas('sync_conflicts', [
            'table_name' => 'loans',
            'row_public_id' => (string) $this->loan->id,
            'reason' => 'server_wins',
        ], 'tenant');
    }

    public function test_push_rejects_non_whitelisted_table(): void
    {
        $mutationUuid = (string) Str::uuid();
        $response = $this->push([[
            'mutation_uuid' => $mutationUuid,
            'table_name' => 'members',
            'operation' => 'update',
            'row_public_id' => 1,
            'payload' => ['member_number' => 'MBR-999'],
            'client_updated_at' => now()->toIso8601String(),
        ]]);

        $response->assertOk()->assertJsonPath('data.rejected.0.reason', 'invalid_mutation_or_table');
    }

    public function test_offline_mode_allows_sync_push_but_blocks_collection_pay(): void
    {
        $cashAccount = Account::on('tenant')->where('is_postable', true)->where('code', 'like', '1.1.01.%')->first();
        $mutationUuid = (string) Str::uuid();

        $syncResponse = $this->withHeader('X-Client-Offline', 'true')
            ->push([$this->paymentMutation($mutationUuid, (int) $cashAccount->row_id)]);
        $syncResponse->assertOk()->assertJsonPath('data.accepted.0', $mutationUuid);

        $payResponse = $this->withHeader('X-Client-Offline', 'true')
            ->postJson("/api/v1/mobile/collection/loans/{$this->loan->row_id}/pay", [
                'member_id' => $this->member->row_id,
                'principal_amount' => 1000,
                'interest_amount' => 100,
            ]);

        $payResponse->assertForbidden()->assertJsonPath('code', 'OFFLINE_READ_ONLY_GUARD');
    }

    public function test_push_rejects_outdated_client_header_before_processing_mutations(): void
    {
        config(['desktop-update.min_version' => '2.0.0']);
        $cashAccount = Account::on('tenant')->where('is_postable', true)->where('code', 'like', '1.1.01.%')->first();
        $mutationUuid = (string) Str::uuid();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->withHeader('X-App-Version', '1.9.0')
            ->postJson('/api/v1/mobile/sync/push', [
                'mutations' => [$this->paymentMutation($mutationUuid, (int) $cashAccount->row_id)],
            ])
            ->assertStatus(426)
            ->assertJsonPath('code', 'CLIENT_OUTDATED')
            ->assertJsonPath('min_supported_version', '2.0.0');

        $this->assertDatabaseMissing('loan_payments', ['reference_number' => $mutationUuid], 'tenant');
    }

    private function pull(array $query = [])
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/mobile/sync/collection'.$this->buildQuery($query));
    }

    private function push(array $mutations)
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mobile/sync/push', ['mutations' => $mutations]);
    }

    private function buildQuery(array $query): string
    {
        return $query === [] ? '' : '?'.http_build_query($query);
    }

    private function paymentMutation(string $mutationUuid, int $cashAccountId): array
    {
        return [
            'mutation_uuid' => $mutationUuid,
            'table_name' => 'loan_payments',
            'operation' => 'insert',
            'row_public_id' => 1,
            'payload' => [
                'loan_row_id' => $this->loan->id,
                'member_id' => $this->member->row_id,
                'cash_account_row_id' => $cashAccountId,
                'transaction_date' => now()->toDateString(),
                'principal_amount' => 1000000,
                'interest_amount' => 120000,
                'penalty_amount' => 0,
                'description' => 'Angsuran offline',
            ],
            'client_updated_at' => now()->toIso8601String(),
        ];
    }

    private function verificationMutation(string $mutationUuid): array
    {
        return [
            'mutation_uuid' => $mutationUuid,
            'table_name' => 'loans',
            'operation' => 'update',
            'row_public_id' => $this->loan->id,
            'payload' => [
                'status' => 'verified',
                'verified_at' => now()->toDateString(),
                'verification_notes' => 'Diverifikasi offline',
            ],
            'client_updated_at' => now()->toIso8601String(),
        ];
    }

    private function createLoan(int $villageId, int $loanId, string $loanNumber): Loan
    {
        $village = OrganizationUnit::query()->create([
            'id' => $villageId,
            'type' => 'village',
            'code' => "33.01.01.200{$villageId}",
            'name' => "Desa {$villageId}",
            'is_active' => true,
        ]);

        $group = Group::query()->withoutGlobalScope(VillageScope::class)->create([
            'id' => $villageId,
            'organization_unit_row_id' => $village->row_id,
            'code' => "GRP-00{$villageId}",
            'name' => "Kelompok {$villageId}",
            'address' => "RT 0{$villageId}",
            'status' => 'active',
        ]);

        $person = Person::query()->create([
            'id' => $villageId,
            'full_name' => "Peminjam {$villageId}",
            'national_identity_number' => "330101500190000{$villageId}",
            'phone' => "08123456789{$villageId}",
            'gender' => 'F',
        ]);

        $member = Member::query()->withoutGlobalScope(VillageScope::class)->create([
            'id' => $villageId,
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => "MBR-00{$villageId}",
            'status' => 'active',
            'registered_at' => now()->toDateString(),
        ]);

        if ($villageId === 1) {
            $this->member = $member;
        }

        $product = LoanProduct::query()->where('code', 'spp')->firstOrFail();
        $loan = Loan::query()->create([
            'id' => $loanId,
            'legacy_source' => 'group_loan',
            'public_id' => (string) Str::ulid(),
            'loan_product_row_id' => $product->row_id,
            'loan_number' => $loanNumber,
            'status' => 'active',
            'principal_amount' => 10000000.00,
            'interest_rate' => 0.0120,
            'term_months' => 10,
            'installment_method' => 'monthly',
            'disbursed_at' => CarbonImmutable::now()->subMonth()->toDateString(),
        ]);

        LoanBorrower::query()->create([
            'id' => $villageId,
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $group->row_id,
        ]);

        LoanBeneficiary::query()->create([
            'id' => $villageId,
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $member->row_id,
            'allocated_amount' => 10000000.00,
        ]);

        LoanInstallment::query()->create([
            'id' => $villageId,
            'loan_row_id' => $loan->row_id,
            'installment_number' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'principal_due' => 1000000.00,
            'interest_due' => 120000.00,
            'principal_paid' => 0.00,
            'interest_paid' => 0.00,
            'status' => 'pending',
        ]);

        return $loan;
    }
}
