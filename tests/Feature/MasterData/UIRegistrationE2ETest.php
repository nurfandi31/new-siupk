<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class UIRegistrationE2ETest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    /** @var array<string, OrganizationUnit> */
    private array $villages;

    /** @var array<string, int> */
    private array $memberRowIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabasesSkippingConflictingMigrations();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'UI Tester',
            'email' => 'ui-tester@example.test',
            'username' => 'ui_tester',
            'password' => 'password',
            'status' => 'active',
        ]);

        $names = ['Sukamaju', 'Sukamakmur', 'Sukaasih', 'Sukaharapan'];
        $this->villages = [];
        foreach ($names as $i => $name) {
            $rowId = $i + 1;
            $this->villages[$name] = OrganizationUnit::query()->create([
                'id' => $rowId,
                'code' => 'V'.str_pad((string) $rowId, 3, '0', STR_PAD_LEFT),
                'name' => 'Desa '.$name,
                'type' => 'village',
                'is_active' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    /**
     * Salinan BuildsTenantTestDatabase::rebuildTenantTestDatabases, tapi skip migration
     * 2026_09_22_000001_add_running_totals_to_loan_installments karena bentrok dengan
     * kolom `component` yang sudah ada dari migration create_lending_tables.
     */
    private function rebuildTenantTestDatabasesSkippingConflictingMigrations(): void
    {
        $skipFiles = ['2026_09_22_000001_add_running_totals_to_loan_installments.php'];

        $platformDb = (string) config('database.connections.platform.database');
        $tenantDb = (string) config('database.connections.tenant.database');
        foreach ([$platformDb, $tenantDb] as $database) {
            if (! is_string($database) || (! str_ends_with($database, '_test') && ! str_contains($database, 'test') && $database !== ':memory:')) {
                throw new \RuntimeException('Integration tests require databases ending with _test or containing test.');
            }
        }

        \DB::connection('platform')->disconnect();
        \DB::connection('tenant')->disconnect();

        \Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        $this->runTenantMigrationsSkipping($skipFiles);

        $this->testTenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->testShard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'test-shard',
            'name' => 'Test Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => (int) config('database.connections.tenant.port', 3306),
            'database_name' => (string) config('database.connections.tenant.database'),
            'credential_reference' => 'test',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $this->testPlacement = TenantPlacement::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'shard_id' => $this->testShard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        \DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $this->testTenant->row_id,
            'public_id' => $this->testTenant->public_id,
            'code' => $this->testTenant->code,
            'name' => $this->testTenant->name,
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TenantContext::class)->initialize(
            $this->testTenant,
            $this->testPlacement,
            $this->testShard,
        );
    }

    /**
     * @param  list<string>  $skipFiles  nama file migrasi (dengan ekstensi) yang harus dilewati
     */
    private function runTenantMigrationsSkipping(array $skipFiles): void
    {
        $path = 'database/migrations/shard';
        $fullPath = base_path($path);
        $files = collect(\File::files($fullPath))
            ->map(fn ($f) => $f->getFilename())
            ->sort()
            ->values();

        \DB::connection('tenant')->disconnect();
        \Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => $path, '--force' => true]);

        foreach ($files as $file) {
            if (in_array($file, $skipFiles, true)) {
                continue;
            }
            \Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => $path.'/'.$file,
                '--force' => true,
            ]);
        }
    }

    public function test_ui_registration_register_12_individuals_then_10_groups(): void
    {
        $individuals = $this->individualPayloads();

        foreach ($individuals as $idx => $payload) {
            $response = $this->actingAs($this->user)
                ->post('/master-data/members', $payload);

            $response->assertRedirect('/master-data/members');
            $this->assertDatabaseHas('people', [
                'tenant_id' => $this->testTenant->row_id,
                'national_identity_number' => $payload['nik'],
                'full_name' => $payload['name'],
            ], 'tenant');
            $memberId = Member::query()->whereHas('person', fn ($q) => $q->where('national_identity_number', $payload['nik']))->value('row_id');
            $this->assertIsInt($memberId);
            $this->memberRowIds[$payload['nik']] = $memberId;
        }

        self::assertCount(12, Member::query()->whereIn('row_id', array_values($this->memberRowIds))->get(), '12 individu harus tersimpan.');
        self::assertCount(12, Person::query()->whereIn('national_identity_number', array_column($individuals, 'nik'))->get(), '12 biodata people harus tersimpan.');

        app(TenantGroupMasterDataProvisioner::class)->ensureDefaults();

        $groups = $this->groupPayloads();
        $created = [];
        foreach ($groups as $idx => $payload) {
            $response = $this->actingAs($this->user)
                ->post('/master-data/groups', $payload);

            $response->assertRedirect('/master-data/groups');
            $group = Group::query()->where('name', $payload['name'])->firstOrFail();
            self::assertSame(3, $group->activeMemberships()->count(), 'Kelompok '.$payload['name'].' harus punya 3 anggota aktif.');
            self::assertSame(3, $group->activeOfficers()->count(), 'Kelompok '.$payload['name'].' harus punya 3 pengurus.');
            self::assertEqualsCanonicalizing(
                ['chair', 'secretary', 'treasurer'],
                $group->activeOfficers()->pluck('position')->all(),
                'Posisi harus lengkap.',
            );
            $created[] = $group->row_id;
        }

        self::assertCount(10, $created, '10 kelompok harus terbuat.');
        self::assertCount(30, GroupMember::query()->whereIn('group_row_id', $created)->where('status', 'active')->get(), 'Total anggota kelompok harus 30.');
        self::assertCount(30, GroupOfficer::query()->whereIn('group_row_id', $created)->whereNull('ended_at')->get(), 'Total pengurus harus 30.');

        $groupPayloadSignature = collect($this->groupPayloads())->map(fn ($p) => $p['name'].'|'.$p['village_id'])->sort()->values()->all();
        $persistedSignature = Group::query()->orderBy('name')->get(['name', 'organization_unit_row_id'])
            ->map(fn (Group $g) => $g->name.'|'.$g->organization_unit_row_id)->all();
        self::assertSame($groupPayloadSignature, $persistedSignature, 'Semua nama kelompok + desa harus cocok.');
    }

    /**
     * Mirip dengan apa yang dikirim form Inertia `useForm().post(path)` ke endpoint.
     *
     * @return list<array<string, mixed>>
     */
    private function individualPayloads(): array
    {
        $today = date('Y-m-d');

        return [
            [
                'nik' => '3210012001000001', 'name' => 'Siti Aminah', 'gender' => 'P',
                'birth_place' => 'Bandung', 'birth_date' => '1990-01-15',
                'phone' => '081234560001', 'family_card_number' => '3210012001000001',
                'address' => 'Jalan Merdeka No. 12 RT 001 RW 002', 'village_id' => $this->villages['Sukamaju']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012001000002', 'name' => 'Dewi Lestari', 'gender' => 'P',
                'birth_place' => 'Jakarta', 'birth_date' => '1992-04-22',
                'phone' => '081234560002', 'family_card_number' => '3210012001000002',
                'address' => 'Jalan Pancasila No. 5 RT 002 RW 001', 'village_id' => $this->villages['Sukamaju']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012001000003', 'name' => 'Ahmad Fauzi', 'gender' => 'L',
                'birth_place' => 'Bogor', 'birth_date' => '1988-07-10',
                'phone' => '081234560003', 'family_card_number' => '3210012001000003',
                'address' => 'Jalan Sudirman No. 21 RT 003 RW 002', 'village_id' => $this->villages['Sukamaju']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012002000004', 'name' => 'Budi Santoso', 'gender' => 'L',
                'birth_place' => 'Surabaya', 'birth_date' => '1991-03-05',
                'phone' => '081234560004', 'family_card_number' => '3210012002000004',
                'address' => 'Jalan Diponegoro No. 8 RT 001 RW 003', 'village_id' => $this->villages['Sukamakmur']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012002000005', 'name' => 'Rina Wulandari', 'gender' => 'P',
                'birth_place' => 'Semarang', 'birth_date' => '1995-09-12',
                'phone' => '081234560005', 'family_card_number' => '3210012002000005',
                'address' => 'Jalan Kartini No. 17 RT 002 RW 002', 'village_id' => $this->villages['Sukamakmur']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012002000006', 'name' => 'Agus Pratama', 'gender' => 'L',
                'birth_place' => 'Yogyakarta', 'birth_date' => '1985-11-30',
                'phone' => '081234560006', 'family_card_number' => '3210012002000006',
                'address' => 'Jalan Wahid Hasyim No. 3 RT 004 RW 001', 'village_id' => $this->villages['Sukamakmur']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012003000007', 'name' => 'Nur Hidayah', 'gender' => 'P',
                'birth_place' => 'Cirebon', 'birth_date' => '1993-06-18',
                'phone' => '081234560007', 'family_card_number' => '3210012003000007',
                'address' => 'Jalan Cempaka No. 9 RT 002 RW 003', 'village_id' => $this->villages['Sukaasih']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012003000008', 'name' => 'Hendra Wijaya', 'gender' => 'L',
                'birth_place' => 'Malang', 'birth_date' => '1987-02-25',
                'phone' => '081234560008', 'family_card_number' => '3210012003000008',
                'address' => 'Jalan Melati No. 14 RT 001 RW 002', 'village_id' => $this->villages['Sukaasih']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012004000009', 'name' => 'Maya Sari', 'gender' => 'P',
                'birth_place' => 'Solo', 'birth_date' => '1994-08-08',
                'phone' => '081234560009', 'family_card_number' => '3210012004000009',
                'address' => 'Jalan Anggrek No. 7 RT 003 RW 001', 'village_id' => $this->villages['Sukaharapan']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012004000010', 'name' => 'Rudi Hermawan', 'gender' => 'L',
                'birth_place' => 'Medan', 'birth_date' => '1989-12-01',
                'phone' => '081234560010', 'family_card_number' => '3210012004000010',
                'address' => 'Jalan Kenanga No. 11 RT 002 RW 002', 'village_id' => $this->villages['Sukaharapan']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012003000011', 'name' => 'Lilis Suryani', 'gender' => 'P',
                'birth_place' => 'Garut', 'birth_date' => '1996-10-20',
                'phone' => '081234560011', 'family_card_number' => '3210012003000011',
                'address' => 'Jalan Mawar No. 22 RT 003 RW 002', 'village_id' => $this->villages['Sukaasih']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
            [
                'nik' => '3210012004000012', 'name' => 'Dedi Kurniawan', 'gender' => 'L',
                'birth_place' => 'Tasik', 'birth_date' => '1992-05-14',
                'phone' => '081234560012', 'family_card_number' => '3210012004000012',
                'address' => 'Jalan Flamboyan No. 5 RT 001 RW 003', 'village_id' => $this->villages['Sukaharapan']->row_id,
                'registered_at' => $today, 'status' => 'active',
                'has_guarantor' => false, 'has_business' => false,
            ],
        ];
    }

    /**
     * Mirip dengan payload form Inertia `Groups/Form.vue` submit (member_ids[] + chair/secretary/treasurer).
     * Aturan: semua anggota & pengurus harus dari desa yang sama dengan village_id kelompok.
     *
     * @return list<array<string, mixed>>
     */
    private function groupPayloads(): array
    {
        $ids = $this->memberRowIds;
        $village = fn (string $name) => $this->villages[$name]->row_id;
        $defaults = $this->groupDefaults();

        return [
            [
                'name' => 'Kelompok Tani Makmur Jaya', 'village_id' => $village('Sukamaju'),
                'address' => 'Jalan Desa Sukamaju No. 1', 'phone' => '081234500001',
                'established_at' => '2020-03-15', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012001000001'], $ids['3210012001000002'], $ids['3210012001000003']],
                'chair_id' => $ids['3210012001000001'], 'secretary_id' => $ids['3210012001000002'], 'treasurer_id' => $ids['3210012001000003'],
            ],
            [
                'name' => 'Kelompok Ternak Sumber Rezeki', 'village_id' => $village('Sukamaju'),
                'address' => 'Jalan Desa Sukamaju No. 25', 'phone' => '081234500002',
                'established_at' => '2020-05-10', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012001000001'], $ids['3210012001000002'], $ids['3210012001000003']],
                'chair_id' => $ids['3210012001000002'], 'secretary_id' => $ids['3210012001000003'], 'treasurer_id' => $ids['3210012001000001'],
            ],
            [
                'name' => 'Kelompok Nelayan Maju Bersama', 'village_id' => $village('Sukamakmur'),
                'address' => 'Jalan Pantai No. 4', 'phone' => '081234500003',
                'established_at' => '2019-11-20', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012002000004'], $ids['3210012002000005'], $ids['3210012002000006']],
                'chair_id' => $ids['3210012002000004'], 'secretary_id' => $ids['3210012002000005'], 'treasurer_id' => $ids['3210012002000006'],
            ],
            [
                'name' => 'Kelompok Anyaman Lestari', 'village_id' => $village('Sukamakmur'),
                'address' => 'Jalan Kerajinan No. 8', 'phone' => '081234500004',
                'established_at' => '2021-02-12', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012002000004'], $ids['3210012002000005'], $ids['3210012002000006']],
                'chair_id' => $ids['3210012002000005'], 'secretary_id' => $ids['3210012002000006'], 'treasurer_id' => $ids['3210012002000004'],
            ],
            [
                'name' => 'Kelompok Tani Tunas Harapan', 'village_id' => $village('Sukaasih'),
                'address' => 'Jalan Pertanian No. 3', 'phone' => '081234500005',
                'established_at' => '2018-08-25', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012003000007'], $ids['3210012003000008'], $ids['3210012003000011']],
                'chair_id' => $ids['3210012003000007'], 'secretary_id' => $ids['3210012003000008'], 'treasurer_id' => $ids['3210012003000011'],
            ],
            [
                'name' => 'Kelompok Usaha Dagang Sukaasih', 'village_id' => $village('Sukaasih'),
                'address' => 'Jalan Pasar No. 11', 'phone' => '081234500006',
                'established_at' => '2022-01-10', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012003000007'], $ids['3210012003000008'], $ids['3210012003000011']],
                'chair_id' => $ids['3210012003000008'], 'secretary_id' => $ids['3210012003000011'], 'treasurer_id' => $ids['3210012003000007'],
            ],
            [
                'name' => 'Kelompok Ternak Ayam Berkah', 'village_id' => $village('Sukaharapan'),
                'address' => 'Jalan Peternakan No. 6', 'phone' => '081234500007',
                'established_at' => '2020-07-18', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012004000009'], $ids['3210012004000010'], $ids['3210012004000012']],
                'chair_id' => $ids['3210012004000009'], 'secretary_id' => $ids['3210012004000010'], 'treasurer_id' => $ids['3210012004000012'],
            ],
            [
                'name' => 'Kelompok Industri Tahu Mandiri', 'village_id' => $village('Sukaharapan'),
                'address' => 'Jalan Industri No. 14', 'phone' => '081234500008',
                'established_at' => '2019-09-05', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012004000009'], $ids['3210012004000010'], $ids['3210012004000012']],
                'chair_id' => $ids['3210012004000010'], 'secretary_id' => $ids['3210012004000012'], 'treasurer_id' => $ids['3210012004000009'],
            ],
            [
                'name' => 'Kelompok Keripik Sukamakmur', 'village_id' => $village('Sukamakmur'),
                'address' => 'Jalan Produksi No. 2', 'phone' => '081234500009',
                'established_at' => '2021-04-22', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012002000004'], $ids['3210012002000005'], $ids['3210012002000006']],
                'chair_id' => $ids['3210012002000004'], 'secretary_id' => $ids['3210012002000006'], 'treasurer_id' => $ids['3210012002000005'],
            ],
            [
                'name' => 'Kelompok Jasa Jahit Sukaasih', 'village_id' => $village('Sukaasih'),
                'address' => 'Jalan Konveksi No. 9', 'phone' => '081234500010',
                'established_at' => '2022-06-30', 'status' => 'active',
                'business_type_id' => $defaults['business_type_id'], 'activity_type_id' => $defaults['activity_type_id'],
                'group_level_id' => $defaults['group_level_id'], 'group_function_id' => $defaults['group_function_id'],
                'member_ids' => [$ids['3210012003000007'], $ids['3210012003000008'], $ids['3210012003000011']],
                'chair_id' => $ids['3210012003000011'], 'secretary_id' => $ids['3210012003000008'], 'treasurer_id' => $ids['3210012003000007'],
            ],
        ];
    }

    /**
     * @return array{business_type_id: int, activity_type_id: int, group_level_id: int, group_function_id: int}
     */
    private function groupDefaults(): array
    {
        return [
            'business_type_id' => (int) BusinessType::query()->where('is_active', true)->orderBy('row_id')->value('row_id'),
            'activity_type_id' => (int) ActivityType::query()->where('is_active', true)->orderBy('row_id')->value('row_id'),
            'group_level_id' => (int) GroupLevel::query()->where('is_active', true)->orderBy('row_id')->value('row_id'),
            'group_function_id' => (int) GroupFunction::query()->where('is_active', true)->orderBy('row_id')->value('row_id'),
        ];
    }
}
