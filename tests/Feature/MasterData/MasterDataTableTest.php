<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MasterDataTableTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware(ResolveTenant::class);
        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Master Data User',
            'email' => 'master-data@example.test',
            'username' => 'master_data_user',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_village_index_searches_sorts_and_paginates_on_the_server(): void
    {
        foreach (range(1, 31) as $number) {
            $this->createUnit($number, 'village', sprintf('V%03d', $number), sprintf('Desa %03d', $number));
        }

        $this->actingAs($this->user)
            ->get('/master-data/villages?per_page=30&sort=code&direction=desc&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MasterData/Villages/Index')
                ->where('perPage', 30)
                ->where('sort', 'code')
                ->where('direction', 'desc')
                ->where('villages.current_page', 2)
                ->where('villages.total', 31)
                ->where('villages.from', 31)
                ->where('villages.to', 31)
                ->has('villages.data', 1)
                ->where('villages.data.0.code', 'V001'));

        $this->actingAs($this->user)
            ->get('/master-data/villages?search=Desa%20017')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('search', 'Desa 017')
                ->where('villages.total', 1)
                ->has('villages.data', 1)
                ->where('villages.data.0.code', 'V017'));
    }

    public function test_table_query_falls_back_for_invalid_parameters(): void
    {
        $this->createUnit(1, 'village', 'B', 'Beta');
        $this->createUnit(2, 'village', 'A', 'Alpha');

        $this->actingAs($this->user)
            ->get('/master-data/villages?per_page=999&sort=tenant_id&direction=sideways')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('perPage', 15)
                ->where('sort', 'name')
                ->where('direction', 'asc')
                ->has('villages.data', 2)
                ->where('villages.data.0.name', 'Alpha'));
    }

    public function test_institution_index_searches_and_sorts_on_the_server(): void
    {
        $village = $this->createUnit(1, 'village', 'V001', 'Desa Induk');
        $this->createUnit(2, 'other_institution', 'L002', 'Koperasi Sejahtera', $village->row_id, [
            'institution_identity_number' => 'INST-002',
            'leader_name' => 'Budi',
        ]);
        $this->createUnit(3, 'other_institution', 'L001', 'Yayasan Makmur', $village->row_id, [
            'institution_identity_number' => 'INST-001',
            'leader_name' => 'Ani',
        ]);

        $this->actingAs($this->user)
            ->get('/master-data/institutions?search=INST&sort=leader_name&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MasterData/Institutions/Index')
                ->where('search', 'INST')
                ->where('sort', 'leader_name')
                ->where('direction', 'asc')
                ->where('institutions.total', 2)
                ->has('institutions.data', 2)
                ->where('institutions.data.0.leader_name', 'Ani')
                ->where('institutions.data.0.village.name', 'Desa Induk'));
    }

    public function test_master_data_indexes_exclude_other_tenants(): void
    {
        $this->createUnit(1, 'village', 'OWN', 'Desa Tenant Sendiri');
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
        DB::connection('tenant')->table('organization_units')->insert([
            'tenant_id' => 999,
            'id' => 1,
            'code' => 'OTHER',
            'name' => 'Desa Tenant Lain',
            'type' => 'village',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get('/master-data/villages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('villages.total', 1)
                ->has('villages.data', 1)
                ->where('villages.data.0.code', 'OWN'));
    }

    private function createUnit(int $id, string $type, string $code, string $name, ?int $parentRowId = null, array $attributes = []): OrganizationUnit
    {
        return OrganizationUnit::query()->create([
            'id' => $id,
            'parent_row_id' => $parentRowId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
