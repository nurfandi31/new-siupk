<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

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
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanFormCommitteeOptionsTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Group $group;

    private Member $chair;

    private Member $secretary;

    private Member $treasurer;

    private Member $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => null,
            'name' => 'Test User',
            'email' => 'committee-form@example.test',
            'username' => 'committee_form_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa A',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-COM',
            'name' => 'Kelompok Tani Maju',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $this->chair = $this->createMember('Andi Ketua', 'KET-COM', '3273010203040001');
        $this->secretary = $this->createMember('Siti Sekretaris', 'SEK-COM', '3273010203040002');
        $this->treasurer = $this->createMember('Budi Bendahara', 'BEN-COM', '3273010203040003');
        $this->outsider = $this->createMember('Non-Pengurus', 'ANG-COM', '3273010203040099');

        foreach (['chair' => $this->chair, 'secretary' => $this->secretary, 'treasurer' => $this->treasurer] as $position => $member) {
            GroupOfficer::query()->create([
                'group_row_id' => $this->group->row_id,
                'member_row_id' => $member->row_id,
                'position' => $position,
                'started_at' => now()->toDateString(),
            ]);
            GroupMember::query()->create([
                'group_row_id' => $this->group->row_id,
                'member_row_id' => $member->row_id,
                'status' => 'active',
                'joined_at' => now()->toDateString(),
            ]);
        }

        // Outsider hanya anggota (bukan pengurus).
        GroupMember::query()->create([
            'group_row_id' => $this->group->row_id,
            'member_row_id' => $this->outsider->row_id,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
    }

    public function test_create_form_provides_committee_members_for_all_active_members(): void
    {
        $response = $this->actingAs($this->user)->get('/lending/loans/create');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Form')
                ->has('committee_members', 4)
                ->where('committee_members.0.label', 'Non-Pengurus · ANG-COM')
            );
    }

    public function test_create_form_keeps_group_officer_snapshot_as_info_only(): void
    {
        $response = $this->actingAs($this->user)->get('/lending/loans/create');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Loans/Form')
                ->has('groups', 1)
                ->where('groups.0.chair.name', 'Andi Ketua')
                ->where('groups.0.secretary.name', 'Siti Sekretaris')
                ->where('groups.0.treasurer.name', 'Budi Bendahara')
            );
    }

    private function createMember(string $fullName, string $memberNumber, string $nik): Member
    {
        $person = Person::query()->create([
            'national_identity_number' => $nik,
            'full_name' => $fullName,
            'gender' => 'L',
        ]);

        return Member::query()->create([
            'organization_unit_row_id' => 1,
            'person_row_id' => $person->row_id,
            'member_number' => $memberNumber,
            'status' => 'active',
            'registered_at' => '2026-01-01',
        ]);
    }
}
