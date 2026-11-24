<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Migration\Lending\LegacyLendingExtractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LegacyLendingExtractorTest extends TestCase
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

        Schema::connection('tenant')->dropIfExists('pinjaman_anggota_99');
        Schema::connection('tenant')->create('pinjaman_anggota_99', function ($table): void {
            $table->bigIncrements('id');
            $table->string('jenis_pinjaman', 1)->default('K');
            $table->unsignedBigInteger('jenis_pp')->default(0);
            $table->unsignedBigInteger('id_pinkel')->default(0);
            $table->unsignedBigInteger('nia')->default(0);
            $table->string('alokasi', 100)->default('0');
        });

        Schema::connection('tenant')->dropIfExists('jenis_produk_pinjaman');
        Schema::connection('tenant')->create('jenis_produk_pinjaman', function ($table): void {
            $table->bigIncrements('id');
            $table->string('kode', 10)->default('');
        });

        Schema::connection('tenant')->dropIfExists('rekening_99');
        Schema::connection('tenant')->create('rekening_99', function ($table): void {
            $table->bigIncrements('id');
            $table->string('kode_akun', 32)->default('');
        });

        Schema::connection('tenant')->dropIfExists('pinjaman_kelompok_99');
        Schema::connection('tenant')->create('pinjaman_kelompok_99', function ($table): void {
            $table->bigIncrements('id');
        });

        Schema::connection('tenant')->dropIfExists('rencana_angsuran_i_99');
        Schema::connection('tenant')->create('rencana_angsuran_i_99', function ($table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->default(0);
            $table->string('angsuran_ke', 3)->default('0');
            $table->date('jatuh_tempo')->nullable();
            $table->bigInteger('wajib_pokok')->default(0);
            $table->bigInteger('wajib_jasa')->default(0);
        });

        Schema::connection('tenant')->dropIfExists('real_angsuran_i_99');
        Schema::connection('tenant')->create('real_angsuran_i_99', function ($table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->default(0);
            $table->date('tgl_transaksi')->nullable();
            $table->bigInteger('realisasi_pokok')->default(0);
            $table->bigInteger('realisasi_jasa')->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('tenant')->dropIfExists('pinjaman_anggota_99');
        Schema::connection('tenant')->dropIfExists('pinjaman_kelompok_99');
        Schema::connection('tenant')->dropIfExists('rencana_angsuran_i_99');
        Schema::connection('tenant')->dropIfExists('real_angsuran_i_99');
        Schema::connection('tenant')->dropIfExists('jenis_produk_pinjaman');
        Schema::connection('tenant')->dropIfExists('rekening_99');
        $this->clearTenantTestContext();

        parent::tearDown();
    }

    public function test_beneficiary_extraction_ignores_individual_loan_rows(): void
    {
        $table = DB::connection('tenant')->table('pinjaman_anggota_99');

        $table->insert([
            ['id' => 1, 'jenis_pinjaman' => 'K', 'id_pinkel' => 10, 'nia' => 1, 'alokasi' => '1000000'],
            ['id' => 2, 'jenis_pinjaman' => 'I', 'id_pinkel' => 0, 'nia' => 2, 'alokasi' => '2000000'],
            ['id' => 3, 'jenis_pinjaman' => 'I', 'id_pinkel' => 0, 'nia' => 3, 'alokasi' => '3000000'],
        ]);

        $extractor = app(LegacyLendingExtractor::class);

        self::assertSame(1, $extractor->pinjamanAnggotaCount('99'));

        $ids = [];
        foreach ($extractor->pinjamanAnggotaChunks('99', 100) as $rows) {
            foreach ($rows as $row) {
                $ids[] = (int) $row->id;
            }
        }

        self::assertSame([1], $ids);
    }

    public function test_individual_loan_and_schedule_extraction_are_separate_from_group_beneficiaries(): void
    {
        $db = DB::connection('tenant');

        $db->table('pinjaman_anggota_99')->insert([
            ['id' => 1, 'jenis_pinjaman' => 'K', 'id_pinkel' => 10, 'nia' => 1, 'alokasi' => '1000000'],
            ['id' => 2, 'jenis_pinjaman' => 'I', 'id_pinkel' => 0, 'nia' => 2, 'alokasi' => '2000000'],
            ['id' => 3, 'jenis_pinjaman' => 'I', 'id_pinkel' => 0, 'nia' => 3, 'alokasi' => '3000000'],
        ]);

        $db->table('rencana_angsuran_i_99')->insert([
            ['id' => 1, 'loan_id' => 2, 'angsuran_ke' => '1', 'jatuh_tempo' => '2026-01-10', 'wajib_pokok' => 100000, 'wajib_jasa' => 5000],
            ['id' => 2, 'loan_id' => 2, 'angsuran_ke' => '0', 'jatuh_tempo' => '2026-01-10', 'wajib_pokok' => 0, 'wajib_jasa' => 0],
            ['id' => 3, 'loan_id' => 3, 'angsuran_ke' => '1', 'jatuh_tempo' => '2026-02-10', 'wajib_pokok' => 200000, 'wajib_jasa' => 8000],
        ]);

        $db->table('real_angsuran_i_99')->insert([
            ['id' => 1, 'loan_id' => 2, 'tgl_transaksi' => '2026-01-11', 'realisasi_pokok' => 100000, 'realisasi_jasa' => 5000],
            ['id' => 2, 'loan_id' => 2, 'tgl_transaksi' => '2026-01-12', 'realisasi_pokok' => 0, 'realisasi_jasa' => 0],
            ['id' => 3, 'loan_id' => 3, 'tgl_transaksi' => '2026-02-11', 'realisasi_pokok' => 200000, 'realisasi_jasa' => 8000],
        ]);

        $extractor = app(LegacyLendingExtractor::class);

        self::assertSame(2, $extractor->pinjamanIndividuCount('99'));
        self::assertSame(2, $extractor->rencanaIndividuCount('99'));
        self::assertSame(2, $extractor->realIndividuCount('99'));

        $ids = [];
        foreach ($extractor->pinjamanIndividuChunks('99', 100) as $rows) {
            foreach ($rows as $row) {
                $ids[] = (int) $row->id;
            }
        }
        self::assertSame([2, 3], $ids);

        $scheduleIds = [];
        foreach ($extractor->rencanaIndividuChunks('99', 100) as $rows) {
            foreach ($rows as $row) {
                $scheduleIds[] = (int) $row->id;
            }
        }
        self::assertSame([1, 3], $scheduleIds);
    }

    public function test_beneficiary_parent_validation_ignores_orphan_parent_rows(): void
    {
        $db = DB::connection('tenant');

        $db->table('pinjaman_kelompok_99')->insert(['id' => 10]);
        $db->table('pinjaman_anggota_99')->insert([
            ['id' => 1, 'jenis_pinjaman' => 'K', 'id_pinkel' => 10, 'nia' => 1, 'alokasi' => '1000000'],
            ['id' => 2, 'jenis_pinjaman' => 'K', 'id_pinkel' => 20, 'nia' => 2, 'alokasi' => '2000000'],
        ]);

        $extractor = app(LegacyLendingExtractor::class);

        self::assertSame(2, $extractor->pinjamanAnggotaCount('99'));
        self::assertSame(1, $extractor->pinjamanAnggotaWithParentCount('99'));
        self::assertSame([10 => true], $extractor->pinjamanKelompokIdSet('99'));
    }

    public function test_extraction_attaches_only_legacy_chart_account_codes(): void
    {
        $db = DB::connection('tenant');

        $db->table('jenis_produk_pinjaman')->insert([
            ['id' => 13, 'kode' => '7'],
            ['id' => 15, 'kode' => '10'],
        ]);
        $db->table('rekening_99')->insert([
            ['kode_akun' => '1.1.03.07'],
            ['kode_akun' => '4.1.01.07'],
            ['kode_akun' => '4.1.02.07'],
        ]);
        $db->table('pinjaman_anggota_99')->insert([
            ['id' => 1, 'jenis_pinjaman' => 'I', 'jenis_pp' => 13, 'id_pinkel' => 0, 'nia' => 1, 'alokasi' => '1000000'],
            ['id' => 2, 'jenis_pinjaman' => 'I', 'jenis_pp' => 15, 'id_pinkel' => 0, 'nia' => 2, 'alokasi' => '2000000'],
        ]);

        $extractor = app(LegacyLendingExtractor::class);
        $rows = [];
        foreach ($extractor->pinjamanIndividuChunks('99', 100) as $chunk) {
            foreach ($chunk as $row) {
                $rows[(int) $row->id] = $row;
            }
        }

        self::assertSame('07', $rows[1]->jpp_kode);
        self::assertSame('1.1.03.07', $rows[1]->jpp_principal_account);
        self::assertSame('4.1.01.07', $rows[1]->jpp_revenue_account);
        self::assertSame('4.1.02.07', $rows[1]->jpp_penalty_account);

        // Kode 10 tidak punya akun di chart legacy ini; jangan meminjam akun Next.
        self::assertSame('10', $rows[2]->jpp_kode);
        self::assertNull($rows[2]->jpp_principal_account);
        self::assertNull($rows[2]->jpp_revenue_account);
        self::assertNull($rows[2]->jpp_penalty_account);
    }
}
