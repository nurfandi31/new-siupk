<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Migration\Savings\SavingsMigrationPipeline;
use App\Domain\Savings\SavingsModule;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LegacySavingsPipelineTest extends TestCase
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

        $this->createLegacySavingsFixture();
    }

    protected function tearDown(): void
    {
        foreach ([
            'jenis_simpanan',
            'simpanan_anggota_99',
            'real_simpanan_99',
            'transaksi_99',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }

        $this->clearTenantTestContext();

        parent::tearDown();
    }

    public function test_pipeline_migrates_accounts_transactions_and_is_idempotent(): void
    {
        $result = app(SavingsMigrationPipeline::class)->run(
            suffix: '99',
            dryRun: false,
            chunk: 100,
            failFast: true,
            skipAccounts: false,
            skipTransactions: false,
            skipReconcile: false,
        );

        self::assertSame('completed', $result['status'], json_encode($result, JSON_PRETTY_PRINT));
        self::assertSame(2, $result['source_products']);
        self::assertSame(2, $result['source_accounts']);
        self::assertSame(4, $result['source_transactions']);
        self::assertSame(2, $result['inserted_accounts']);
        self::assertSame(4, $result['inserted_transactions']);
        self::assertSame(0, $result['opening_transactions']);

        $tenantId = app(TenantContext::class)->id();
        $accounts = SavingsModule::table('accounts', $tenantId);
        $transactions = SavingsModule::table('transactions', $tenantId);
        $db = DB::connection('tenant');

        $first = $db->table($accounts)
            ->where('tenant_id', $tenantId)
            ->where('member_row_id', $this->memberRowId())
            ->orderBy('row_id')
            ->first();

        self::assertNotNull($first);
        self::assertSame('1-001.6396-11-1', $first->account_number);
        self::assertSame('80000.00', number_format((float) $first->balance, 2, '.', ''));
        self::assertSame(3, $db->table($transactions)->where('account_row_id', $first->row_id)->count());

        $second = $db->table($accounts)
            ->where('tenant_id', $tenantId)
            ->where('row_id', '!=', $first->row_id)
            ->orderBy('row_id')
            ->first();

        self::assertNotNull($second);
        self::assertSame('1-001.6396-11-1-2', $second->account_number);
        self::assertSame('closed', $second->status);
        self::assertSame('0.00', number_format((float) $second->balance, 2, '.', ''));

        $codes = $db->table($transactions)
            ->where('account_row_id', $first->row_id)
            ->orderBy('row_id')
            ->pluck('code')
            ->map(fn ($v) => (int) $v)
            ->all();
        self::assertSame([1, 3, 4], $codes);

        $recon = collect($result['recon'])->keyBy('scope');
        self::assertSame('matched', $recon['savings_accounts']['status']);
        self::assertSame('matched', $recon['savings_transactions']['status']);
        self::assertSame('matched', $recon['savings_balance']['status']);

        $again = app(SavingsMigrationPipeline::class)->run(
            suffix: '99',
            dryRun: false,
            chunk: 100,
            failFast: true,
            skipAccounts: false,
            skipTransactions: false,
            skipReconcile: false,
        );

        self::assertSame('completed', $again['status'], json_encode($again, JSON_PRETTY_PRINT));
        self::assertSame(0, $again['inserted_accounts']);
        self::assertSame(0, $again['inserted_transactions']);
        self::assertSame(2, $db->table($accounts)->where('tenant_id', $tenantId)->count());
        self::assertSame(4, $db->table($transactions)->where('tenant_id', $tenantId)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $result = app(SavingsMigrationPipeline::class)->run(
            suffix: '99',
            dryRun: true,
            chunk: 100,
            failFast: true,
            skipAccounts: false,
            skipTransactions: false,
            skipReconcile: false,
        );

        self::assertSame('dry_run_ok', $result['status'], json_encode($result, JSON_PRETTY_PRINT));
        self::assertSame(2, $result['source_accounts']);
        self::assertSame(2, $result['would_insert_accounts']);
        self::assertSame(4, $result['would_insert_transactions']);
        self::assertNull($result['batch_row_id']);

        $tenantId = app(TenantContext::class)->id();
        self::assertFalse(Schema::connection('tenant')->hasTable(SavingsModule::table('accounts', $tenantId)));
    }

    private function createLegacySavingsFixture(): void
    {
        $db = DB::connection('tenant');
        $tenantId = app(TenantContext::class)->id();
        $now = now()->format('Y-m-d H:i:s');

        $db->table('people')->insert([
            'tenant_id' => $tenantId,
            'id' => 1000,
            'public_id' => (string) Str::ulid(),
            'full_name' => 'Anggota Simpanan',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $personRowId = (int) $db->table('people')
            ->where('tenant_id', $tenantId)
            ->where('id', 1000)
            ->value('row_id');

        $db->table('members')->insert([
            'tenant_id' => $tenantId,
            'id' => 100,
            'public_id' => (string) Str::ulid(),
            'person_row_id' => $personRowId,
            'member_number' => 'A-100',
            'registered_at' => '2024-01-01',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::connection('tenant')->create('jenis_simpanan', function ($table): void {
            $table->bigIncrements('id');
            $table->string('nama_js', 50)->nullable();
            $table->string('deskripsi_js', 50)->nullable();
            $table->string('file', 1)->default('1');
            $table->decimal('saldo_minimal', 15, 2)->default(0);
        });
        $db->table('jenis_simpanan')->insert([
            ['id' => 1, 'nama_js' => 'Simpanan Umum', 'deskripsi_js' => 'Umum', 'file' => '1', 'saldo_minimal' => 20000],
            ['id' => 2, 'nama_js' => 'Simpanan Berjangka', 'deskripsi_js' => 'Deposito', 'file' => '2', 'saldo_minimal' => 50000],
        ]);

        Schema::connection('tenant')->create('simpanan_anggota_99', function ($table): void {
            $table->bigIncrements('id');
            $table->string('nomor_rekening', 30)->nullable();
            $table->integer('jenis_simpanan')->default(0);
            $table->string('nia', 30)->nullable();
            $table->string('jumlah', 10)->nullable();
            $table->date('tgl_buka')->nullable();
            $table->date('tgl_tutup')->nullable();
            $table->date('tgl_st')->nullable();
            $table->string('bunga', 6)->nullable();
            $table->string('pajak', 6)->nullable();
            $table->string('admin', 7)->nullable();
            $table->string('status', 1)->nullable();
            $table->integer('sp')->default(0);
            $table->string('lembaga', 20)->nullable();
            $table->string('jabatan', 20)->nullable();
            $table->string('pengampu', 20)->nullable();
            $table->string('hubungan', 3)->nullable();
            $table->text('catatan_simpanan')->nullable();
        });
        $db->table('simpanan_anggota_99')->insert([
            [
                'id' => 1,
                'nomor_rekening' => '1-001.6396-11-1',
                'jenis_simpanan' => 1,
                'nia' => '100',
                'jumlah' => '100000',
                'tgl_buka' => '2025-01-01',
                'tgl_tutup' => '2025-12-31',
                'tgl_st' => null,
                'bunga' => '1.5',
                'pajak' => '10',
                'admin' => '1000',
                'status' => 'A',
                'sp' => 1,
            ],
            [
                'id' => 2,
                'nomor_rekening' => '1-001.6396-11-1',
                'jenis_simpanan' => 2,
                'nia' => '100',
                'jumlah' => '50000',
                'tgl_buka' => '2025-01-15',
                'tgl_tutup' => '2025-06-30',
                'tgl_st' => '2025-06-30',
                'bunga' => '2',
                'pajak' => '10',
                'admin' => '0',
                'status' => 'T',
                'sp' => 1,
            ],
        ]);

        Schema::connection('tenant')->create('real_simpanan_99', function ($table): void {
            $table->bigIncrements('id');
            $table->string('cif', 50)->nullable();
            $table->integer('idt')->default(0);
            $table->string('tgl_transaksi', 10)->nullable();
            $table->bigInteger('real_d')->default(0);
            $table->bigInteger('real_k')->default(0);
            $table->bigInteger('sum')->nullable();
        });
        $db->table('real_simpanan_99')->insert([
            ['id' => 1, 'cif' => '1', 'idt' => 1, 'tgl_transaksi' => '2025-01-01', 'real_d' => 0, 'real_k' => 100000, 'sum' => 100000],
            ['id' => 2, 'cif' => '1', 'idt' => 2, 'tgl_transaksi' => '2025-02-01', 'real_d' => 25000, 'real_k' => 0, 'sum' => 75000],
            ['id' => 3, 'cif' => '1', 'idt' => 3, 'tgl_transaksi' => '2025-03-01', 'real_d' => 0, 'real_k' => 5000, 'sum' => 80000],
            ['id' => 4, 'cif' => '2', 'idt' => 4, 'tgl_transaksi' => '2025-06-30', 'real_d' => 50000, 'real_k' => 0, 'sum' => 0],
            ['id' => 5, 'cif' => '1', 'idt' => 5, 'tgl_transaksi' => '2025-04-01', 'real_d' => 0, 'real_k' => 0, 'sum' => 80000],
        ]);

        Schema::connection('tenant')->create('transaksi_99', function ($table): void {
            $table->bigIncrements('idt');
            $table->date('tgl_transaksi')->nullable();
            $table->string('rekening_debit', 10)->nullable();
            $table->string('rekening_kredit', 10)->nullable();
            $table->string('id_simp', 10)->nullable();
            $table->text('keterangan_transaksi')->nullable();
            $table->bigInteger('jumlah')->nullable();
        });
        $db->table('transaksi_99')->insert([
            ['idt' => 1, 'tgl_transaksi' => '2025-01-01', 'keterangan_transaksi' => 'Setoran Awal'],
            ['idt' => 2, 'tgl_transaksi' => '2025-02-01', 'keterangan_transaksi' => 'Penarikan Simpanan'],
            ['idt' => 3, 'tgl_transaksi' => '2025-03-01', 'keterangan_transaksi' => 'Bunga Simpanan'],
            ['idt' => 4, 'tgl_transaksi' => '2025-06-30', 'keterangan_transaksi' => 'Penutupan Rekening'],
        ]);
    }

    private function memberRowId(): int
    {
        return (int) DB::connection('tenant')->table('members')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('id', 100)
            ->value('row_id');
    }
}
