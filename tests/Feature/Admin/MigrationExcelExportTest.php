<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

final class MigrationExcelExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        config([
            'database.connections.legacy' => array_merge(config('database.connections.tenant'), [
                'name' => 'legacy',
                'database' => config('database.connections.platform.database'),
            ]),
        ]);
        DB::purge('legacy');

        $this->createFixtures();
    }

    public function test_group_loan_excel_export_contains_source_and_installment_sheets(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->get('/admin/migration/export/group-loans?suffix=101');

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $archive = $this->openArchive($response->streamedContent());

        self::assertStringContainsString('Kelompok Melati', $this->sharedStrings($archive));
        self::assertStringContainsString('Pinjaman Kelompok', $this->workbook($archive));
        self::assertStringContainsString('Rencana Angsuran', $this->workbook($archive));
        self::assertStringContainsString('Realisasi Angsuran', $this->workbook($archive));

        $archive->close();
    }

    public function test_member_loan_excel_export_contains_individual_rows_only(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->get('/admin/migration/export/member-loans?suffix=101');

        $response->assertOk();

        $archive = $this->openArchive($response->streamedContent());
        $strings = $this->sharedStrings($archive);

        self::assertStringContainsString('Budi', $strings);
        self::assertStringContainsString('Kelompok Melati', $strings);
        self::assertStringContainsString('Pinjaman Individu', $this->workbook($archive));
        self::assertStringContainsString('Rencana Angsuran Individu', $this->workbook($archive));
        self::assertStringContainsString('Realisasi Angsuran Individu', $this->workbook($archive));

        $archive->close();
    }

    public function test_savings_excel_export_contains_product_balance_and_mutation_sheets(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->get('/admin/migration/export/savings?suffix=101');

        $response->assertOk();

        $archive = $this->openArchive($response->streamedContent());
        $strings = $this->sharedStrings($archive);

        self::assertStringContainsString('Simpanan Sukarela', $strings);
        self::assertStringContainsString('Budi', $strings);
        self::assertStringContainsString('Rekening Simpanan', $this->workbook($archive));
        self::assertStringContainsString('Mutasi Simpanan', $this->workbook($archive));
        self::assertStringContainsString('300000', $archive->getFromName('xl/worksheets/sheet1.xml'));

        $archive->close();
    }

    public function test_export_requires_numeric_suffix(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/migration/export/group-loans?suffix=abc')
            ->assertStatus(422);
    }

    private function openArchive(string $content): ZipArchive
    {
        self::assertStringStartsWith('PK', $content);

        $path = tempnam(sys_get_temp_dir(), 'xlsx_test_').'.xlsx';
        file_put_contents($path, $content);

        $archive = new ZipArchive;
        self::assertTrue($archive->open($path) === true);
        @unlink($path);

        return $archive;
    }

    private function sharedStrings(ZipArchive $archive): string
    {
        return (string) $archive->getFromName('xl/sharedStrings.xml');
    }

    private function workbook(ZipArchive $archive): string
    {
        return (string) $archive->getFromName('xl/workbook.xml');
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }

    private function createFixtures(): void
    {
        foreach ([
            'kelompok_101',
            'anggota_101',
            'jenis_simpanan',
            'pinjaman_kelompok_101',
            'pinjaman_anggota_101',
            'rencana_angsuran_101',
            'real_angsuran_101',
            'rencana_angsuran_i_101',
            'real_angsuran_i_101',
            'simpanan_anggota_101',
            'real_simpanan_101',
            'transaksi_101',
        ] as $table) {
            Schema::connection('legacy')->dropIfExists($table);
        }

        Schema::connection('legacy')->create('kelompok_101', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama_kelompok');
        });
        Schema::connection('legacy')->create('anggota_101', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('namadepan');
        });
        Schema::connection('legacy')->create('jenis_simpanan', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama_js');
        });
        Schema::connection('legacy')->create('pinjaman_kelompok_101', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('id_kel')->default('0');
            $table->integer('jenis_pp')->default(0);
            $table->string('alokasi')->default('0');
            $table->string('status')->default('A');
        });
        Schema::connection('legacy')->create('pinjaman_anggota_101', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('jenis_pinjaman')->default('K');
            $table->string('id_kel')->default('0');
            $table->string('id_pinkel')->default('0');
            $table->string('nia')->default('0');
            $table->string('alokasi')->default('0');
            $table->string('kom_pokok')->default('0');
            $table->string('kom_jasa')->default('0');
            $table->string('status')->default('A');
        });
        foreach (['rencana_angsuran_101', 'rencana_angsuran_i_101'] as $tableName) {
            Schema::connection('legacy')->create($tableName, function ($table): void {
                $table->unsignedBigInteger('id')->primary();
                $table->string('loan_id')->default('0');
                $table->string('angsuran_ke')->default('0');
                $table->date('jatuh_tempo')->nullable();
                $table->integer('wajib_pokok')->default(0);
                $table->integer('wajib_jasa')->default(0);
                $table->integer('target_pokok')->default(0);
                $table->integer('target_jasa')->default(0);
            });
        }
        foreach (['real_angsuran_101', 'real_angsuran_i_101'] as $tableName) {
            Schema::connection('legacy')->create($tableName, function ($table): void {
                $table->unsignedBigInteger('id')->primary();
                $table->string('loan_id')->default('0');
                $table->date('tgl_transaksi')->nullable();
                $table->integer('realisasi_pokok')->default(0);
                $table->integer('realisasi_jasa')->default(0);
                $table->integer('sum_pokok')->default(0);
                $table->integer('sum_jasa')->default(0);
                $table->integer('saldo_pokok')->default(0);
                $table->integer('saldo_jasa')->default(0);
                $table->integer('tunggakan_pokok')->default(0);
                $table->integer('tunggakan_jasa')->default(0);
            });
        }
        Schema::connection('legacy')->create('simpanan_anggota_101', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nomor_rekening');
            $table->integer('jenis_simpanan')->default(1);
            $table->string('nia');
            $table->string('jumlah')->default('0');
            $table->date('tgl_buka')->nullable();
            $table->string('status')->default('A');
        });
        Schema::connection('legacy')->create('real_simpanan_101', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('cif');
            $table->integer('idt')->default(0);
            $table->string('tgl_transaksi')->default('');
            $table->bigInteger('real_d')->default(0);
            $table->bigInteger('real_k')->default(0);
            $table->bigInteger('sum')->default(0);
        });
        Schema::connection('legacy')->create('transaksi_101', function ($table): void {
            $table->unsignedBigInteger('idt')->primary();
            $table->string('keterangan_transaksi')->default('');
            $table->string('rekening_debit')->default('');
            $table->string('rekening_kredit')->default('');
        });

        DB::connection('legacy')->table('kelompok_101')->insert(['id' => 10, 'nama_kelompok' => 'Kelompok Melati']);
        DB::connection('legacy')->table('anggota_101')->insert(['id' => 20, 'namadepan' => 'Budi']);
        DB::connection('legacy')->table('jenis_simpanan')->insert(['id' => 1, 'nama_js' => 'Simpanan Sukarela']);
        DB::connection('legacy')->table('pinjaman_kelompok_101')->insert([
            'id' => 1,
            'id_kel' => '10',
            'jenis_pp' => 1,
            'alokasi' => '5000000',
            'status' => 'A',
        ]);
        DB::connection('legacy')->table('pinjaman_anggota_101')->insert([
            'id' => 2,
            'jenis_pinjaman' => 'I',
            'id_kel' => '10',
            'id_pinkel' => '0',
            'nia' => '20',
            'alokasi' => '1500000',
            'kom_pokok' => '0',
            'kom_jasa' => '0',
            'status' => 'A',
        ]);
        DB::connection('legacy')->table('rencana_angsuran_101')->insert([
            'id' => 1,
            'loan_id' => '1',
            'angsuran_ke' => '1',
            'jatuh_tempo' => '2026-01-01',
            'wajib_pokok' => 500000,
            'wajib_jasa' => 50000,
        ]);
        DB::connection('legacy')->table('real_angsuran_101')->insert([
            'id' => 1,
            'loan_id' => '1',
            'tgl_transaksi' => '2026-02-01',
            'realisasi_pokok' => 500000,
            'realisasi_jasa' => 50000,
            'sum_pokok' => 500000,
            'sum_jasa' => 50000,
        ]);
        DB::connection('legacy')->table('rencana_angsuran_i_101')->insert([
            'id' => 1,
            'loan_id' => '2',
            'angsuran_ke' => '1',
            'jatuh_tempo' => '2026-01-01',
            'wajib_pokok' => 150000,
            'wajib_jasa' => 15000,
        ]);
        DB::connection('legacy')->table('real_angsuran_i_101')->insert([
            'id' => 1,
            'loan_id' => '2',
            'tgl_transaksi' => '2026-02-01',
            'realisasi_pokok' => 150000,
            'realisasi_jasa' => 15000,
            'sum_pokok' => 150000,
            'sum_jasa' => 15000,
        ]);
        DB::connection('legacy')->table('simpanan_anggota_101')->insert([
            'id' => 100,
            'nomor_rekening' => 'S-001',
            'jenis_simpanan' => 1,
            'nia' => '20',
            'jumlah' => '250000',
            'tgl_buka' => '2026-01-01',
            'status' => 'A',
        ]);
        DB::connection('legacy')->table('real_simpanan_101')->insert([
            'id' => 1,
            'cif' => '100',
            'idt' => 9,
            'tgl_transaksi' => '2026-02-01',
            'real_d' => 0,
            'real_k' => 300000,
            'sum' => 300000,
        ]);
        DB::connection('legacy')->table('transaksi_101')->insert([
            'idt' => 9,
            'keterangan_transaksi' => 'Setoran Tunai',
            'rekening_debit' => '101',
            'rekening_kredit' => 'S-001',
        ]);
    }
}
