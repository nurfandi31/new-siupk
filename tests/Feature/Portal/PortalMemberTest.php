<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberUserLink;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class PortalMemberTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private Member $member;

    private Member $otherMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Induk',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->member = $this->createMember('Portal Anggota', 'M0001');
        $this->otherMember = $this->createMember('Anggota Lain', 'M0002');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_user_with_other_role_cannot_access_portal(): void
    {
        $user = $this->createUser('staff');
        $this->assignRole($user, 'viewer');

        $this->actingAs($user)->get('/portal')->assertForbidden();
    }

    public function test_linked_member_sees_only_own_loan_data(): void
    {
        $user = $this->createUser('member_user');
        $this->assignRole($user, 'anggota');
        MemberUserLink::query()->create([
            'user_row_id' => $user->row_id,
            'member_row_id' => $this->member->row_id,
        ]);

        $ownLoan = $this->createLoan($this->member, 'LN-OWN', '2026-01-05', 1000000, true);
        $otherLoan = $this->createLoan($this->otherMember, 'LN-OTHER', '2026-02-05', 2500000, false);

        $response = $this->actingAs($user)->get('/portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Index')
                ->where('profile.member_number', 'M0001')
                ->where('loan_summary.total', 1)
                ->where('loan_summary.total_disbursed', 1000000)
                ->where('loan_summary.active_count', 1)
                ->where('loans.data.0.id', 1)
                ->where('loans.data.0.has_arrears', true)
                ->where('loans.data.0.due', 1100000)
                ->where('loans.data.0.paid', 350000)
                ->where('loans.data.0.installments.0.status', 'partial')
                ->where('loans.data.0.installments.1.status', 'pending'));

        self::assertMatchesRegularExpression('/&quot;id&quot;:'.((int) $ownLoan->id).',/', (string) $response->getContent());
        self::assertDoesNotMatchRegularExpression('/&quot;id&quot;:'.((int) $otherLoan->id).',/', (string) $response->getContent());
        self::assertDoesNotMatchRegularExpression('/&quot;LN-OTHER&quot;/', (string) $response->getContent());
    }

    public function test_linked_member_sees_loan_beneficiaries_with_private_amounts(): void
    {
        $user = $this->createUser('member_user');
        $this->assignRole($user, 'anggota');
        MemberUserLink::query()->create([
            'user_row_id' => $user->row_id,
            'member_row_id' => $this->member->row_id,
        ]);

        $loan = $this->createLoan($this->member, 'LN-BENEFICIARIES', '2026-01-05', 1000000, false);
        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $this->otherMember->row_id,
            'allocated_amount' => 600000,
        ]);
        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $this->member->row_id,
            'allocated_amount' => 400000,
        ]);

        $response = $this->actingAs($user)->get('/portal');
        $page = $response->viewData('page');
        $loans = $page['props']['loans']['data'];
        $beneficiaries = $loans[0]['beneficiaries'];

        $response->assertOk();
        self::assertSame(
            [$this->member->row_id, $this->otherMember->row_id],
            array_column($beneficiaries, 'member_row_id'),
        );
        self::assertSame('Portal Anggota', $beneficiaries[0]['name']);
        self::assertSame('Anggota Lain', $beneficiaries[1]['name']);
        self::assertTrue($beneficiaries[0]['is_self']);
        self::assertFalse($beneficiaries[1]['is_self']);
        self::assertSame(400000.0, $beneficiaries[0]['allocated_amount']);
        self::assertNull($beneficiaries[1]['allocated_amount']);
        self::assertArrayNotHasKey('proposed_amount', $beneficiaries[0]);
        self::assertArrayNotHasKey('verified_amount', $beneficiaries[0]);
        self::assertArrayNotHasKey('written_off_amount', $beneficiaries[0]);
        self::assertArrayNotHasKey('written_off_at', $beneficiaries[0]);
        self::assertArrayNotHasKey('written_off_reason', $beneficiaries[0]);
    }

    public function test_loan_without_beneficiaries_returns_empty_list(): void
    {
        $user = $this->createUser('member_user');
        $this->assignRole($user, 'anggota');
        MemberUserLink::query()->create([
            'user_row_id' => $user->row_id,
            'member_row_id' => $this->member->row_id,
        ]);
        $this->createLoan($this->member, 'LN-NO-BENEFICIARIES', '2026-01-05', 1000000, false);

        $response = $this->actingAs($user)->get('/portal');
        $page = $response->viewData('page');
        $loans = $page['props']['loans']['data'];

        $response->assertOk();
        self::assertSame([], $loans[0]['beneficiaries']);
    }

    public function test_active_officer_sees_fellow_group_members(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-AKTIF',
            'name' => 'Kelompok Aktif',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);
        GroupMember::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->member->row_id,
            'joined_at' => '2026-01-01',
            'status' => 'active',
        ]);
        GroupMember::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->otherMember->row_id,
            'joined_at' => '2026-01-01',
            'status' => 'active',
        ]);
        GroupOfficer::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->member->row_id,
            'position' => 'chair',
            'started_at' => '2026-01-01',
        ]);

        $user = $this->createUser('active_officer');
        $this->assignRole($user, 'anggota');
        MemberUserLink::query()->create([
            'user_row_id' => $user->row_id,
            'member_row_id' => $this->member->row_id,
        ]);

        $this->actingAs($user)->get('/portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Index')
                ->where('officers.0.position', 'chair')
                ->where('active_groups.0.group_name', 'Kelompok Aktif')
                ->where('active_groups.0.members.0.member_number', 'M0002'));
    }

    public function test_inactive_officer_does_not_see_fellow_group_members(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-LAMA',
            'name' => 'Kelompok Lama',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);
        GroupMember::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->otherMember->row_id,
            'joined_at' => '2026-01-01',
            'status' => 'active',
        ]);
        GroupOfficer::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->member->row_id,
            'position' => 'treasurer',
            'started_at' => '2025-01-01',
            'ended_at' => '2025-12-31',
        ]);

        $user = $this->createUser('inactive_officer');
        $this->assignRole($user, 'anggota');
        MemberUserLink::query()->create([
            'user_row_id' => $user->row_id,
            'member_row_id' => $this->member->row_id,
        ]);

        $this->actingAs($user)->get('/portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Index')
                ->where('officers.0.ended_at', '2025-12-31')
                ->missing('active_groups.0'));
    }

    public function test_admin_store_and_update_manage_member_link(): void
    {
        $admin = $this->createUser('admin_user');
        app(PermissionChecker::class)->ensureSystemRoles();

        $this->actingAs($admin)->post('/access/users', [
            'name' => 'Linked Member',
            'username' => 'linked_member',
            'email' => 'linked@example.test',
            'phone' => '081400000001',
            'password' => 'password',
            'password_confirmation' => 'password',
            'status' => 'active',
            'role' => 'anggota',
            'member_row_id' => $this->member->row_id,
        ])->assertRedirect('/access/users');

        $linkedUser = User::query()->where('username', 'linked_member')->firstOrFail();
        self::assertSame($this->member->row_id, (int) MemberUserLink::query()->where('user_row_id', $linkedUser->row_id)->value('member_row_id'));

        $this->actingAs($admin)->put('/access/users/'.$linkedUser->row_id, [
            'name' => 'Linked Member',
            'username' => 'linked_member',
            'email' => 'linked@example.test',
            'phone' => '081400000001',
            'password' => '',
            'password_confirmation' => '',
            'status' => 'active',
            'role' => 'viewer',
        ])->assertRedirect('/access/users');

        self::assertFalse(MemberUserLink::query()->where('user_row_id', $linkedUser->row_id)->exists());
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => ucfirst($username),
            'email' => "{$username}@example.test",
            'username' => $username,
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    private function assignRole(User $user, string $role): void
    {
        app(PermissionChecker::class)->assignRole($user, $role);
    }

    private function createMember(string $name, string $memberNumber): Member
    {
        $person = Person::query()->create(['full_name' => $name]);

        return Member::query()->create([
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => 1,
            'member_number' => $memberNumber,
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    private function createLoan(Member $member, string $loanNumber, string $disbursedAt, float $principal, bool $withArrears): Loan
    {
        $loan = Loan::query()->create([
            'legacy_source' => 'member_loan',
            'loan_product_row_id' => 1,
            'principal_amount' => $principal,
            'disbursed_at' => $disbursedAt,
            'status' => 'active',
            'term_months' => 2,
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $member->row_id,
        ]);

        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'installment_number' => 1,
            'due_date' => '2026-01-10',
            'principal_due' => 500000,
            'interest_due' => 50000,
            'principal_paid' => 300000,
            'interest_paid' => 50000,
            'status' => $withArrears ? 'partial' : 'paid',
            'paid_at' => '2026-01-09 10:00:00',
        ]);
        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'installment_number' => 2,
            'due_date' => '2026-02-10',
            'principal_due' => 500000,
            'interest_due' => 50000,
            'principal_paid' => $withArrears ? 0 : 500000,
            'interest_paid' => $withArrears ? 0 : 50000,
            'status' => $withArrears ? 'pending' : 'paid',
            'paid_at' => $withArrears ? null : '2026-02-09 10:00:00',
        ]);

        return $loan;
    }
}
