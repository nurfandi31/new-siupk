<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MemberService;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MemberTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private OrganizationUnit $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);
        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Member User',
            'email' => 'member@example.test',
            'username' => 'member_user',
            'password' => 'password',
            'status' => 'active',
        ]);
        $this->village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Induk',
            'type' => 'village',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_lookup_returns_complete_existing_member_data(): void
    {
        $member = $this->createMember([
            'has_guarantor' => true,
            'guarantor_nik' => '3273010203040002',
            'guarantor_name' => 'Siti Penjamin',
            'guarantor_relationship' => 'Saudara',
            'has_business' => true,
            'business_name' => 'Warung Makmur',
            'business_description' => 'Usaha sembako',
        ]);

        $this->actingAs($this->user)
            ->getJson('/master-data/members/lookup?nik=3273010203040001')
            ->assertOk()
            ->assertJsonPath('data.row_id', $member->row_id)
            ->assertJsonPath('data.name', 'Budi Anggota')
            ->assertJsonPath('data.village_id', $this->village->row_id)
            ->assertJsonPath('data.guarantor.nik', '3273010203040002')
            ->assertJsonPath('data.business.name', 'Warung Makmur');
    }

    public function test_lookup_validates_nik_and_returns_null_when_unknown(): void
    {
        $this->actingAs($this->user)
            ->getJson('/master-data/members/lookup?nik=123')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nik');

        $this->actingAs($this->user)
            ->getJson('/master-data/members/lookup?nik=3273010203049999')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_lookup_does_not_expose_another_tenants_member(): void
    {
        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => 999,
            'public_id' => (string) Str::ulid(),
            'code' => 'other-tenant',
            'name' => 'Other Tenant',
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $personId = DB::connection('tenant')->table('people')->insertGetId([
            'tenant_id' => 999,
            'id' => 1,
            'public_id' => (string) Str::ulid(),
            'national_identity_number' => '3273010203040003',
            'full_name' => 'Anggota Tenant Lain',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'row_id');
        DB::connection('tenant')->table('members')->insert([
            'tenant_id' => 999,
            'id' => 1,
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $personId,
            'member_number' => '3273010203040003',
            'registered_at' => '2026-07-19',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson('/master-data/members/lookup?nik=3273010203040003')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_update_changes_existing_member_without_creating_duplicates(): void
    {
        $member = $this->createMember();
        $payload = $this->memberData([
            'name' => 'Budi Diperbarui',
            'phone' => '081299999999',
            'address' => 'Alamat Baru',
        ]);

        $this->actingAs($this->user)
            ->put('/master-data/members/'.$member->row_id, $payload)
            ->assertRedirect('/master-data/members');

        self::assertSame(1, Member::query()->count());
        self::assertSame(1, DB::connection('tenant')->table('people')->where('tenant_id', $this->testTenant->row_id)->count());
        $member->refresh()->load(['person', 'address']);
        self::assertSame('Budi Diperbarui', $member->person->full_name);
        self::assertSame('081299999999', $member->person->phone);
        self::assertSame('Alamat Baru', $member->address->address);
    }

    public function test_duplicate_nik_on_create_remains_rejected(): void
    {
        $this->createMember();

        $this->actingAs($this->user)
            ->post('/master-data/members', $this->memberData())
            ->assertSessionHasErrors('nik');

        self::assertSame(1, Member::query()->count());
    }

    public function test_destroy_soft_deletes_member_and_redirects(): void
    {
        $member = $this->createMember();
        $memberId = $member->row_id;

        $this->actingAs($this->user)
            ->delete('/master-data/members/'.$memberId)
            ->assertRedirect('/master-data/members');

        self::assertNull(Member::query()->find($memberId));
        self::assertNotNull(Member::withTrashed()->find($memberId)->deleted_at);
    }

    public function test_destroy_rejects_member_registered_in_group(): void
    {
        $member = $this->createMember();

        $group = Group::query()->create([
            'code' => 'KLP-01',
            'name' => 'Kelompok 1',
            'organization_unit_row_id' => $this->village->row_id,
            'status' => 'active',
        ]);

        GroupMember::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $member->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->delete('/master-data/members/'.$member->row_id);

        $response->assertSessionHas('error');
        self::assertNotNull(Member::query()->find($member->row_id));
    }

    public function test_destroy_requires_authentication(): void
    {
        $member = $this->createMember();
        $memberId = $member->row_id;

        $response = $this->delete('/master-data/members/'.$memberId);
        $response->assertStatus(302);
        $this->assertGuest();

        $still = Member::query()->find($memberId);
        self::assertNotNull($still);
        self::assertNull($still->deleted_at);
    }

    public function test_destroy_rejects_already_soft_deleted_member(): void
    {
        $member = $this->createMember();
        $member->delete();

        $this->actingAs($this->user)
            ->delete('/master-data/members/'.$member->row_id)
            ->assertNotFound();
    }

    private function createMember(array $overrides = []): Member
    {
        return app(MemberService::class)->create($this->memberData($overrides), (int) $this->user->row_id);
    }

    private function memberData(array $overrides = []): array
    {
        return [
            'nik' => '3273010203040001',
            'name' => 'Budi Anggota',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '1990-01-02',
            'phone' => '081234567890',
            'family_card_number' => '3273010203040004',
            'address' => 'Jalan Desa',
            'village_id' => $this->village->row_id,
            'registered_at' => '2026-07-19',
            'status' => 'active',
            'has_guarantor' => false,
            'has_business' => false,
            ...$overrides,
        ];
    }
}
