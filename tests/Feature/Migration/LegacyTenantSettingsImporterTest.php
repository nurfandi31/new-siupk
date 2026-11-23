<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Migration\Settings\LegacyTenantSettingsImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

/**
 * Memastikan pengaturan `kecamatan` legacy (pembulatan & batas tanggal cair)
 * benar-benar masuk ke profil tenant Next. Tanpa ini, jadwal hasil hitung ulang
 * (simulasi/dokumen pra-cair) memakai default '5000' dan angkanya meleset.
 */
final class LegacyTenantSettingsImporterTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rebuildTenantTestDatabases();

        config([
            'database.connections.legacy' => array_merge(config('database.connections.tenant'), [
                'name' => 'legacy',
            ]),
        ]);
        DB::purge('legacy');

        Schema::connection('tenant')->create('kecamatan', function ($table): void {
            $table->integer('id')->primary();
            $table->string('nama_kec')->nullable();
            $table->string('nama_lembaga_long')->nullable();
            $table->string('nama_lembaga_sort')->nullable();
            $table->string('nama_bkad_sort')->nullable();
            $table->string('alamat_kec')->nullable();
            $table->string('telpon_kec')->nullable();
            $table->string('email_kec')->nullable();
            $table->string('pembulatan')->nullable();
            $table->integer('batas_angsuran')->default(0);
            $table->string('def_bunga')->default('0');
            $table->string('def_pajak_bunga')->default('0');
            $table->integer('hitung_bunga')->default(3);
            $table->integer('tgl_bunga')->default(0);
            $table->integer('min_bunga')->default(0);
            $table->integer('min_pajak')->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('tenant')->dropIfExists('kecamatan');
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_imports_legacy_rounding_and_cutoff_into_profile(): void
    {
        $this->insertKecamatan('500', 0);

        $result = app(LegacyTenantSettingsImporter::class)
            ->import('3', dryRun: false, withSavings: false);

        self::assertSame('500', $result['installment_rounding']);
        self::assertSame(0, $result['disbursement_cutoff_day']);
        self::assertTrue($result['profile_created']);

        $profile = OrganizationProfile::query()->firstOrFail();
        self::assertSame('500', $profile->installment_rounding);
        self::assertSame(0, (int) $profile->disbursement_cutoff_day);
        self::assertSame('Koperasi Uji', $profile->legal_name);
    }

    public function test_negative_and_plus_rounding_modes_are_preserved(): void
    {
        $this->insertKecamatan('-500', 10);

        app(LegacyTenantSettingsImporter::class)->import('3', dryRun: false, withSavings: false);

        $profile = OrganizationProfile::query()->firstOrFail();
        self::assertSame('-500', $profile->installment_rounding);
        self::assertSame(10, (int) $profile->disbursement_cutoff_day);

        $this->replaceKecamatan('+1000', 25);
        app(LegacyTenantSettingsImporter::class)->import('3', dryRun: false, withSavings: false);

        $profile->refresh();
        self::assertSame('+1000', $profile->installment_rounding);
        self::assertSame(25, (int) $profile->disbursement_cutoff_day);
    }

    public function test_zero_rounding_maps_to_legacy_no_rounding_sentinel(): void
    {
        $this->insertKecamatan('0', 31);

        app(LegacyTenantSettingsImporter::class)->import('3', dryRun: false, withSavings: false);

        $profile = OrganizationProfile::query()->firstOrFail();
        self::assertSame('5000', $profile->installment_rounding);
        // Dipangkas ke 28 (batas aman tanggal).
        self::assertSame(28, (int) $profile->disbursement_cutoff_day);
    }

    public function test_dry_run_does_not_write_profile(): void
    {
        $this->insertKecamatan('500', 0);

        $result = app(LegacyTenantSettingsImporter::class)
            ->import('3', dryRun: true, withSavings: false);

        self::assertSame('500', $result['installment_rounding']);
        self::assertNull(OrganizationProfile::query()->first());
    }

    private function insertKecamatan(string $rounding, int $cutoff): void
    {
        DB::connection('tenant')->table('kecamatan')->insert([
            'id' => 3,
            'nama_kec' => 'Kecamatan Uji',
            'nama_lembaga_long' => 'Koperasi Uji',
            'nama_lembaga_sort' => 'KU',
            'nama_bkad_sort' => 'KU',
            'alamat_kec' => 'Jl. Uji No. 1',
            'telpon_kec' => '081200000000',
            'email_kec' => 'uji@example.test',
            'pembulatan' => $rounding,
            'batas_angsuran' => $cutoff,
        ]);
    }

    private function replaceKecamatan(string $rounding, int $cutoff): void
    {
        DB::connection('tenant')->table('kecamatan')->where('id', 3)->update([
            'pembulatan' => $rounding,
            'batas_angsuran' => $cutoff,
        ]);
    }
}
