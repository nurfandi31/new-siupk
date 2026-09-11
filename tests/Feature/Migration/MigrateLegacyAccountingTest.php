<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Migration\Accounting\AccountingMigrationPipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MigrateLegacyAccountingTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->pointLegacyAtTenant();
        $this->createFixtureTables();
        $this->seedAccountsAndPeriod();
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
        $result = app(AccountingMigrationPipeline::class)->run(
            suffix: '99',
            dryRun: true,
            chunk: 100,
            fromDate: null,
            toDate: null,
            failFast: true,
            skipOpenings: false,
            skipJournals: false,
            skipRecalc: true,
            skipReconcile: true,
        );

        self::assertSame('dry_run_ok', $result['status']);
        self::assertSame(1, $result['would_insert_journals']);
        self::assertSame(0, (int) DB::connection('tenant')->table('journal_entries')->count());
    }

    public function test_migrate_inserts_journal_and_is_idempotent(): void
    {
        $pipeline = app(AccountingMigrationPipeline::class);

        $first = $pipeline->run(
            suffix: '99',
            dryRun: false,
            chunk: 100,
            fromDate: null,
            toDate: null,
            failFast: true,
            skipOpenings: false,
            skipJournals: false,
            skipRecalc: true,
            skipReconcile: false,
        );

        self::assertSame('completed', $first['status'], json_encode($first));
        self::assertSame(1, $first['inserted_journals']);
        self::assertSame(1, (int) DB::connection('tenant')->table('journal_entries')->count());
        self::assertSame(2, (int) DB::connection('tenant')->table('journal_lines')->count());

        $entry = DB::connection('tenant')->table('journal_entries')->first();
        self::assertSame(1001, (int) $entry->id);
        self::assertSame('posted', $entry->status);
        self::assertSame('legacy_transaksi', $entry->source_type);
        self::assertSame('1001', (string) $entry->journal_number);

        $second = $pipeline->run(
            suffix: '99',
            dryRun: false,
            chunk: 100,
            fromDate: null,
            toDate: null,
            failFast: true,
            skipOpenings: false,
            skipJournals: false,
            skipRecalc: true,
            skipReconcile: true,
        );

        self::assertSame(0, $second['inserted_journals']);
        self::assertSame(1, $second['would_skip_journals']);
        self::assertSame(1, (int) DB::connection('tenant')->table('journal_entries')->count());
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
        $schema->dropIfExists('transaksi_99');
        $schema->dropIfExists('saldo_99');

        $schema->create('transaksi_99', function ($table): void {
            $table->bigIncrements('idt');
            $table->date('tgl_transaksi');
            $table->string('rekening_debit', 50);
            $table->string('rekening_kredit', 50);
            $table->string('jumlah', 100);
            $table->unsignedInteger('urutan')->default(0);
            $table->integer('idtp')->default(0);
            $table->integer('id_pinj')->default(0);
            $table->integer('id_pinj_i')->default(0);
            $table->string('keterangan_transaksi', 255)->nullable();
            $table->string('relasi', 255)->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('saldo_99', function ($table): void {
            $table->string('id', 64)->primary();
            $table->string('kode_akun', 50);
            $table->unsignedSmallInteger('tahun');
            $table->string('bulan', 4);
            $table->string('debit', 100)->default('0');
            $table->string('kredit', 100)->default('0');
        });
    }

    private function dropFixtureTables(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('transaksi_99');
        $schema->dropIfExists('saldo_99');
    }

    private function seedAccountsAndPeriod(): void
    {
        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        $cash = Account::query()->create([
            'code' => '1.1.01.01',
            'name' => 'Kas Tunai',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        Account::query()->create([
            'code' => '3.1.01.01',
            'name' => 'Modal',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        // silence unused in static analysis paths
        unset($cash);
    }

    private function seedLegacyRows(): void
    {
        DB::connection('tenant')->table('transaksi_99')->insert([
            'idt' => 1001,
            'tgl_transaksi' => '2026-07-10',
            'rekening_debit' => '1.1.01.01',
            'rekening_kredit' => '3.1.01.01',
            'jumlah' => '1.500.000,00',
            'urutan' => 1,
            'idtp' => 0,
            'id_pinj' => 0,
            'id_pinj_i' => 0,
            'keterangan_transaksi' => 'Setor modal',
            'relasi' => '-',
            'id_user' => 1,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // soft-deleted must be ignored
        DB::connection('tenant')->table('transaksi_99')->insert([
            'idt' => 1002,
            'tgl_transaksi' => '2026-07-11',
            'rekening_debit' => '1.1.01.01',
            'rekening_kredit' => '3.1.01.01',
            'jumlah' => '100000',
            'urutan' => 1,
            'idtp' => 0,
            'id_pinj' => 0,
            'id_pinj_i' => 0,
            'keterangan_transaksi' => 'deleted',
            'relasi' => null,
            'id_user' => 1,
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('saldo_99')->insert([
            'id' => '110101202600',
            'kode_akun' => '1.1.01.01',
            'tahun' => 2026,
            'bulan' => '0',
            'debit' => '100000',
            'kredit' => '25000',
        ]);
    }
}
