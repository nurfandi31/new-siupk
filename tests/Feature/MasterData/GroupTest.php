<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberAddress;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\MemberService;
use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class GroupTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private OrganizationUnit $village;

    private array $members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);
        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(), 'tenant_id' => $this->testTenant->row_id,
            'name' => 'Group User', 'email' => 'group@example.test', 'username' => 'group_user',
            'password' => 'password', 'status' => 'active',
        ]);
        $this->village = OrganizationUnit::query()->create(['id' => 1, 'code' => 'V001', 'name' => 'Desa Induk', 'type' => 'village', 'is_active' => true]);
        app(TenantGroupMasterDataProvisioner::class)->ensureDefaults();
        $service = app(MemberService::class);
        $this->members = [];
        foreach (range(1, 4) as $number) {
            $this->members[] = $service->create($this->memberData($number), (int) $this->user->row_id);
        }
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_master_data_provisioning_is_idempotent(): void
    {
        app(TenantGroupMasterDataProvisioner::class)->ensureDefaults();
        self::assertEqualsCanonicalizing(['Aneka Usaha', 'Usaha Bersama'], BusinessType::query()->where('is_active', true)->pluck('name')->all());
        self::assertEqualsCanonicalizing(['Industri Rumah Tangga', 'Jasa', 'Kerajinan', 'Perdagangan', 'Perikanan', 'Pertanian', 'Peternakan'], ActivityType::query()->where('is_active', true)->pluck('name')->all());
        self::assertEqualsCanonicalizing(['Berkembang', 'Pemula', 'Siap'], GroupLevel::query()->where('is_active', true)->pluck('name')->all());
        self::assertEqualsCanonicalizing(['Channeling', 'Executing'], GroupFunction::query()->where('is_active', true)->pluck('name')->all());
    }

    public function test_create_group_persists_members_and_three_distinct_officers(): void
    {
        $this->actingAs($this->user)->post('/master-data/groups', $this->groupData())->assertRedirect('/master-data/groups');

        $group = Group::query()->firstOrFail();
        self::assertMatchesRegularExpression('/^\d{14}$/', $group->code);
        self::assertSame(3, $group->activeMemberships()->count());
        self::assertSame(3, $group->activeOfficers()->count());
        self::assertEqualsCanonicalizing(['chair', 'secretary', 'treasurer'], $group->activeOfficers()->pluck('position')->all());
    }

    public function test_validation_rejects_duplicate_or_non_member_officers(): void
    {
        $duplicate = $this->groupData(['secretary_id' => $this->members[0]->row_id]);
        $this->actingAs($this->user)->post('/master-data/groups', $duplicate)->assertSessionHasErrors('secretary_id');

        $outside = $this->groupData(['treasurer_id' => $this->members[3]->row_id]);
        $this->actingAs($this->user)->post('/master-data/groups', $outside)->assertSessionHasErrors('treasurer_id');
        self::assertSame(0, Group::query()->count());
    }

    public function test_update_closes_removed_membership_and_changed_officer_period(): void
    {
        $this->actingAs($this->user)->post('/master-data/groups', $this->groupData());
        $group = Group::query()->firstOrFail();
        $oldChair = $group->activeOfficers()->where('position', 'chair')->firstOrFail();
        $updated = $this->groupData([
            'name' => 'Kelompok Diperbarui',
            'member_ids' => [$this->members[1]->row_id, $this->members[2]->row_id, $this->members[3]->row_id],
            'chair_id' => $this->members[3]->row_id,
            'secretary_id' => $this->members[1]->row_id,
            'treasurer_id' => $this->members[2]->row_id,
        ]);

        $this->actingAs($this->user)->put('/master-data/groups/'.$group->row_id, $updated)->assertRedirect('/master-data/groups');

        self::assertSame('Kelompok Diperbarui', $group->fresh()->name);
        self::assertSame(3, $group->activeMemberships()->count());
        self::assertNotNull(GroupMember::query()->where('group_row_id', $group->row_id)->where('member_row_id', $this->members[0]->row_id)->firstOrFail()->left_at);
        self::assertNotNull($oldChair->fresh()->ended_at);
        self::assertSame($this->members[3]->row_id, $group->activeOfficers()->where('position', 'chair')->value('member_row_id'));
    }

    public function test_create_group_rejects_members_or_officers_from_other_village(): void
    {
        $otherVillage = OrganizationUnit::query()->create(['id' => 2, 'code' => 'V002', 'name' => 'Desa Tetangga', 'type' => 'village', 'is_active' => true]);
        $service = app(MemberService::class);
        $payload = $this->memberData(99);
        $payload['nik'] = '3273010203040099';
        $payload['name'] = 'Anggota Desa Tetangga';
        $payload['village_id'] = $otherVillage->row_id;
        $outsider = $service->create($payload, (int) $this->user->row_id);

        $response = $this->actingAs($this->user)
            ->post('/master-data/groups', $this->groupData([
                'member_ids' => [$outsider->row_id, $this->members[1]->row_id, $this->members[2]->row_id],
                'chair_id' => $outsider->row_id,
            ]));

        $response->assertSessionHasErrors(['chair_id', 'member_ids.0']);
        $chairError = $response->getSession()->get('errors')->get('chair_id')[0];
        $memberError = $response->getSession()->get('errors')->get('member_ids.0')[0];
        self::assertStringContainsString('Anggota Desa Tetangga', $chairError);
        self::assertStringContainsString('3273010203040099', $chairError);
        self::assertStringContainsString('Desa Tetangga', $chairError);
        self::assertStringContainsString('Anggota Desa Tetangga', $memberError);

        self::assertSame(0, Group::query()->count());
    }

    public function test_destroy_soft_deletes_empty_group_and_redirects(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-EMPTY',
            'name' => 'Kelompok Kosong',
            'organization_unit_row_id' => $this->village->row_id,
            'status' => 'active',
        ]);

        $this->actingAs($this->user)
            ->delete('/master-data/groups/'.$group->row_id)
            ->assertRedirect('/master-data/groups');

        self::assertNull(Group::query()->find($group->row_id));
        self::assertNotNull(Group::withTrashed()->find($group->row_id)->deleted_at);
    }

    public function test_destroy_rejects_group_with_active_members(): void
    {
        $this->actingAs($this->user)->post('/master-data/groups', $this->groupData());
        $group = Group::query()->firstOrFail();

        $response = $this->actingAs($this->user)
            ->delete('/master-data/groups/'.$group->row_id);

        $response->assertSessionHas('error');
        self::assertNotNull(Group::query()->find($group->row_id));
    }

    public function test_destroy_requires_authentication(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-EMPTY',
            'name' => 'Kelompok Kosong',
            'organization_unit_row_id' => $this->village->row_id,
            'status' => 'active',
        ]);
        $groupId = $group->row_id;

        // Forget auth state explicitly so we can verify the unauthenticated path.
        auth()->guard('web')->forgetUser();
        $this->app['auth']->forgetGuards();

        $response = $this->delete('/master-data/groups/'.$groupId);
        $response->assertStatus(302);

        $still = Group::query()->find($groupId);
        self::assertNotNull($still);
        self::assertNull($still->deleted_at);
    }

    public function test_destroy_rejects_already_soft_deleted_group(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-EMPTY',
            'name' => 'Kelompok Kosong',
            'organization_unit_row_id' => $this->village->row_id,
            'status' => 'active',
        ]);
        $group->delete();

        $this->actingAs($this->user)
            ->delete('/master-data/groups/'.$group->row_id)
            ->assertNotFound();
    }

    public function test_member_options_only_return_active_current_tenant_members(): void
    {
        $this->members[3]->update(['status' => 'exited']);
        $this->actingAs($this->user)->getJson('/master-data/groups/member-options?search=Anggota')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_member_options_excludes_already_selected_ids(): void
    {
        $ids = [$this->members[0]->row_id, $this->members[1]->row_id];
        $query = http_build_query(['search' => 'Anggota', 'exclude' => $ids]);
        $response = $this->actingAs($this->user)
            ->getJson("/master-data/groups/member-options?{$query}")
            ->assertOk();

        $values = collect($response->json('data'))->pluck('value')->all();
        self::assertNotContains($this->members[0]->row_id, $values);
        self::assertNotContains($this->members[1]->row_id, $values);
        self::assertContains($this->members[2]->row_id, $values);
        self::assertContains($this->members[3]->row_id, $values);
    }

    public function test_member_options_ignore_non_integer_exclude_values(): void
    {
        $query = http_build_query(['search' => 'Anggota', 'exclude' => ['abc', null, '9']]);
        $this->actingAs($this->user)
            ->getJson("/master-data/groups/member-options?{$query}")
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_quick_member_registration_uses_server_controlled_defaults(): void
    {
        $today = now()->toDateString();
        $payload = [
            'nik' => '3273010203049999',
            'name' => 'Anggota Ringkas',
            'gender' => 'P',
            'village_id' => $this->village->row_id,
            'address' => 'Alamat palsu',
            'registered_at' => '2000-01-01',
            'status' => 'deceased',
            'tenant_id' => 999,
        ];

        $response = $this->actingAs($this->user)->postJson('/master-data/groups/members', $payload)
            ->assertCreated()
            ->assertJsonPath('data.nik', $payload['nik'])
            ->assertJsonPath('data.name', $payload['name']);

        $member = Member::query()->findOrFail($response->json('data.value'));
        self::assertSame('active', $member->status);
        self::assertSame($today, $member->registered_at->format('Y-m-d'));
        self::assertSame($this->village->row_id, $member->organization_unit_row_id);
        self::assertSame('Desa Induk', $member->address()->value('address'));
        self::assertSame(0, $member->business()->count());
        self::assertSame(0, $member->guarantor()->count());
    }

    public function test_quick_member_registration_rejects_invalid_or_duplicate_data_without_partial_records(): void
    {
        $people = Person::query()->count();
        $members = Member::query()->count();
        $addresses = MemberAddress::query()->count();

        $this->actingAs($this->user)->postJson('/master-data/groups/members', [
            'nik' => $this->members[0]->person->national_identity_number,
            'name' => 'Duplikat',
            'gender' => 'X',
            'village_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['nik', 'gender', 'village_id']);

        $this->actingAs($this->user)->postJson('/master-data/groups/members', [
            'nik' => '123',
            'name' => 'NIK Tidak Valid',
            'gender' => 'L',
            'village_id' => $this->village->row_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('nik');

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $this->testTenant->row_id + 1,
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherVillage = DB::connection('tenant')->table('organization_units')->insertGetId([
            'tenant_id' => $this->testTenant->row_id + 1,
            'id' => 1,
            'code' => 'V-B001',
            'name' => 'Desa Tenant B',
            'type' => 'village',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'row_id');
        $this->actingAs($this->user)->postJson('/master-data/groups/members', [
            'nik' => '3273010203049997',
            'name' => 'Lintas Tenant',
            'gender' => 'P',
            'village_id' => $otherVillage,
        ])->assertUnprocessable()->assertJsonValidationErrors('village_id');

        self::assertSame($people, Person::query()->count());
        self::assertSame($members, Member::query()->count());
        self::assertSame($addresses, MemberAddress::query()->count());
    }

    public function test_quick_member_registration_rejects_inactive_village(): void
    {
        $this->village->update(['is_active' => false]);

        $this->actingAs($this->user)->postJson('/master-data/groups/members', [
            'nik' => '3273010203049998',
            'name' => 'Anggota Ditolak',
            'gender' => 'L',
            'village_id' => $this->village->row_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('village_id');

        self::assertSame(4, Member::query()->count());
    }

    private function groupData(array $overrides = []): array
    {
        return [
            'village_id' => $this->village->row_id,
            'business_type_id' => BusinessType::query()->where('is_active', true)->value('row_id'),
            'activity_type_id' => ActivityType::query()->where('is_active', true)->value('row_id'),
            'group_level_id' => GroupLevel::query()->where('is_active', true)->value('row_id'),
            'group_function_id' => GroupFunction::query()->where('is_active', true)->value('row_id'),
            'name' => 'Kelompok Makmur', 'address' => 'Jalan Desa', 'phone' => '081234567890',
            'established_at' => '2020-01-02', 'status' => 'active',
            'member_ids' => [$this->members[0]->row_id, $this->members[1]->row_id, $this->members[2]->row_id],
            'chair_id' => $this->members[0]->row_id,
            'secretary_id' => $this->members[1]->row_id,
            'treasurer_id' => $this->members[2]->row_id,
            ...$overrides,
        ];
    }

    private function memberData(int $number): array
    {
        return [
            'nik' => sprintf('327301020304%04d', $number), 'name' => "Anggota {$number}", 'gender' => 'L',
            'birth_place' => null, 'birth_date' => null, 'phone' => null, 'family_card_number' => null,
            'address' => 'Jalan Desa', 'village_id' => $this->village->row_id,
            'registered_at' => '2026-07-19', 'status' => 'active', 'has_guarantor' => false, 'has_business' => false,
        ];
    }
}
