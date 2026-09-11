<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Migration\Membership\MembershipMigrationPipeline;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MigrateLegacyMembershipTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->pointLegacyAtTenant();
        $this->createFixtureTables();
        app(TenantGroupMasterDataProvisioner::class)->ensureDefaults();
        $this->seedLegacyRows();
    }

    protected function tearDown(): void
    {
        $this->dropFixtureTables();
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $result = app(MembershipMigrationPipeline::class)->run(
            suffix: '99',
            dryRun: true,
            chunk: 100,
            failFast: true,
            skipMembers: false,
            skipGroups: false,
            skipReconcile: true,
        );

        self::assertSame('dry_run_ok', $result['status'], json_encode($result));
        self::assertSame(1, $result['would_insert_members']);
        self::assertSame(1, $result['would_insert_groups']);
        self::assertSame(0, (int) DB::connection('tenant')->table('members')->count());
        self::assertSame(0, (int) DB::connection('tenant')->table('groups')->count());
    }

    public function test_migrate_inserts_member_group_and_is_idempotent(): void
    {
        $pipeline = app(MembershipMigrationPipeline::class);

        $first = $pipeline->run(
            suffix: '99',
            dryRun: false,
            chunk: 100,
            failFast: true,
            skipMembers: false,
            skipGroups: false,
            skipReconcile: false,
        );

        self::assertSame('completed', $first['status'], json_encode($first));
        self::assertSame(1, $first['inserted_members']);
        self::assertSame(1, $first['inserted_groups']);
        self::assertSame(1, (int) DB::connection('tenant')->table('members')->count());
        self::assertSame(1, (int) DB::connection('tenant')->table('groups')->count());

        $member = DB::connection('tenant')->table('members')->first();
        self::assertSame(501, (int) $member->id);
        self::assertSame('3201010101010001', $member->member_number);

        $person = DB::connection('tenant')->table('people')->where('id', 501)->first();
        self::assertNotNull($person);
        self::assertSame('Siti Aminah', $person->full_name);
        self::assertSame('P', $person->gender);

        $group = DB::connection('tenant')->table('groups')->first();
        self::assertSame(10, (int) $group->id);
        self::assertSame('Kelompok Mawar', $group->name);

        self::assertSame(1, (int) DB::connection('tenant')->table('group_members')->count());
        self::assertSame(1, (int) DB::connection('tenant')->table('group_officers')->count());
        self::assertSame(1, (int) DB::connection('tenant')->table('member_guarantors')->count());

        $second = $pipeline->run(
            suffix: '99',
            dryRun: false,
            chunk: 100,
            failFast: true,
            skipMembers: false,
            skipGroups: false,
            skipReconcile: true,
        );

        self::assertSame(0, $second['inserted_members']);
        self::assertSame(1, $second['would_skip_members']);
        self::assertSame(1, (int) DB::connection('tenant')->table('members')->count());
        self::assertSame(1, (int) DB::connection('tenant')->table('groups')->count());
    }

    private function pointLegacyAtTenant(): void
    {
        $tenant = config('database.connections.tenant');
        config([
            'database.connections.legacy' => array_merge($tenant, [
                'name' => 'legacy',
            ]),
        ]);
        DB::purge('legacy');
    }

    private function createFixtureTables(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('anggota_99');
        $schema->dropIfExists('kelompok_99');

        $schema->create('anggota_99', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nik', 32)->nullable();
            $table->string('namadepan', 180);
            $table->string('jk', 10)->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tgl_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('desa', 100)->nullable();
            $table->unsignedInteger('lokasi')->nullable();
            $table->string('hp', 30)->nullable();
            $table->string('kk', 32)->nullable();
            $table->string('nik_penjamin', 32)->nullable();
            $table->string('penjamin', 180)->nullable();
            $table->string('hubungan', 50)->nullable();
            $table->string('usaha', 180)->nullable();
            $table->date('terdaftar')->nullable();
            $table->string('status', 30)->nullable();
            $table->string('petugas', 50)->nullable();
        });

        $schema->create('kelompok_99', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedInteger('lokasi')->nullable();
            $table->string('desa', 100)->nullable();
            $table->string('kd_kelompok', 80)->nullable();
            $table->string('nama_kelompok', 225);
            $table->text('alamat_kelompok')->nullable();
            $table->string('telpon', 30)->nullable();
            $table->date('tgl_berdiri')->nullable();
            $table->string('ketua', 180)->nullable();
            $table->string('sekretaris', 180)->nullable();
            $table->string('bendahara', 180)->nullable();
        });
    }

    private function dropFixtureTables(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('anggota_99');
        $schema->dropIfExists('kelompok_99');
    }

    private function seedLegacyRows(): void
    {
        DB::connection('tenant')->table('anggota_99')->insert([
            'id' => 501,
            'nik' => '3201010101010001',
            'namadepan' => 'Siti Aminah',
            'jk' => 'P',
            'tempat_lahir' => 'Bantul',
            'tgl_lahir' => '1990-01-01',
            'alamat' => 'Jl. Melati 1',
            'desa' => null,
            'lokasi' => 1,
            'hp' => '08123456789',
            'kk' => '3201010101010001',
            'nik_penjamin' => '3201010101010099',
            'penjamin' => 'Budi Santoso',
            'hubungan' => '1',
            'usaha' => 'Warung',
            'terdaftar' => '2020-01-15',
            'status' => '1',
            'petugas' => null,
        ]);

        DB::connection('tenant')->table('kelompok_99')->insert([
            'id' => 10,
            'lokasi' => 1,
            'desa' => null,
            'kd_kelompok' => 'K-10',
            'nama_kelompok' => 'Kelompok Mawar',
            'alamat_kelompok' => 'Dusun 1',
            'telpon' => '08',
            'tgl_berdiri' => '2019-06-01',
            'ketua' => '501',
            'sekretaris' => '0',
            'bendahara' => '0',
        ]);
    }
}
