<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Migration\Lending\LendingMigrationPipeline;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LegacyMemberLendingPipelineTest extends TestCase
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

        $this->createLegacyLendingFixture();
    }

    protected function tearDown(): void
    {
        foreach ([
            'pinjaman_anggota_99',
            'rencana_angsuran_i_99',
            'real_angsuran_i_99',
            'pinjaman_kelompok_99',
            'rencana_angsuran_99',
            'real_angsuran_99',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }

        $this->clearTenantTestContext();

        parent::tearDown();
    }

    public function test_pipeline_loads_individual_loans_schedules_and_payments(): void
    {
        $result = app(LendingMigrationPipeline::class)->run(
            suffix: '99',
            dryRun: false,
            chunk: 100,
            failFast: true,
            skipLoans: true,
            skipBeneficiaries: true,
            skipInstallments: true,
            skipPayments: true,
            skipReconcile: false,
            skipMemberLoans: false,
            skipMemberInstallments: false,
            skipMemberPayments: false,
        );

        self::assertSame('completed', $result['status'], json_encode($result, JSON_PRETTY_PRINT));
        self::assertSame(1, $result['source_member_loans']);
        self::assertSame(1, $result['source_member_installments']);
        self::assertSame(1, $result['source_member_payments']);
        self::assertSame(1, $result['inserted_member_loans']);
        self::assertSame(1, $result['inserted_member_installments']);
        self::assertSame(1, $result['inserted_member_payments']);

        $tenantId = app(TenantContext::class)->id();
        $db = DB::connection('tenant');

        $loan = $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->where('legacy_source', 'member_loan')
            ->where('id', 2)
            ->first();

        self::assertNotNull($loan);
        self::assertSame('PI-2', $loan->loan_number);
        self::assertSame('2000000.00', number_format((float) $loan->principal_amount, 2, '.', ''));
        self::assertSame('active', $loan->status);
        self::assertSame('pi', $db->table('loan_products')->where('row_id', $loan->loan_product_row_id)->value('code'));

        $borrower = $db->table('loan_borrowers')
            ->where('tenant_id', $tenantId)
            ->where('loan_row_id', $loan->row_id)
            ->first();
        self::assertNotNull($borrower);
        self::assertSame($this->memberRowId(), (int) $borrower->member_row_id);
        self::assertNull($borrower->group_row_id);

        $installments = $db->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->where('loan_row_id', $loan->row_id)
            ->orderBy('component')
            ->get();
        self::assertCount(2, $installments);
        self::assertSame('interest', $installments[0]->component);
        self::assertSame('principal', $installments[1]->component);

        $principal = $installments[1];
        self::assertSame('100000.00', number_format((float) $principal->principal_due, 2, '.', ''));
        self::assertSame('100000.00', number_format((float) $principal->principal_paid, 2, '.', ''));
        self::assertSame('0.00', number_format((float) $principal->interest_paid, 2, '.', ''));

        $interest = $installments[0];
        self::assertSame('5000.00', number_format((float) $interest->interest_due, 2, '.', ''));
        self::assertSame('5000.00', number_format((float) $interest->interest_paid, 2, '.', ''));

        $payment = $db->table('loan_payments')
            ->where('tenant_id', $tenantId)
            ->where('loan_row_id', $loan->row_id)
            ->first();
        self::assertNotNull($payment);
        self::assertSame('RPI-1', $payment->payment_number);
        self::assertSame('105000.00', number_format((float) $payment->amount, 2, '.', ''));

        $recon = collect($result['recon'])->keyBy('scope');
        self::assertSame('matched', $recon['member_loans']['status']);
        self::assertSame('matched', $recon['member_installments']['status']);
        self::assertSame('matched', $recon['member_payments']['status']);
        self::assertSame('matched', $recon['member_loan_balance']['status']);
    }

    private function createLegacyLendingFixture(): void
    {
        $db = DB::connection('tenant');
        $tenantId = app(TenantContext::class)->id();
        $now = now()->format('Y-m-d H:i:s');

        $db->table('people')->insert([
            'tenant_id' => $tenantId,
            'id' => 1000,
            'public_id' => (string) Str::ulid(),
            'full_name' => 'Peminjam Individu',
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

        Schema::connection('tenant')->create('pinjaman_anggota_99', function ($table): void {
            $table->bigIncrements('id');
            $table->string('jenis_pinjaman', 1)->default('I');
            $table->unsignedBigInteger('id_pinkel')->default(0);
            $table->unsignedBigInteger('nia')->default(0);
            $table->integer('jenis_pp')->default(1);
            $table->string('alokasi', 100)->default('0');
            $table->string('pros_jasa', 20)->default('0');
            $table->string('jangka', 5)->default('0');
            $table->string('status', 2)->default('A');
            $table->integer('sistem_angsuran')->default(0);
            $table->string('spk_no', 50)->nullable();
            $table->date('tgl_proposal')->nullable();
            $table->date('tgl_verifikasi')->nullable();
            $table->date('tgl_dana')->nullable();
            $table->date('tgl_tunggu')->nullable();
            $table->date('tgl_cair')->nullable();
            $table->date('tgl_lunas')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->text('catatan_bimbingan')->nullable();
            $table->string('waktu_verifikasi', 20)->nullable();
            $table->string('wt_cair', 50)->nullable();
        });
        $db->table('pinjaman_anggota_99')->insert([
            'id' => 2,
            'jenis_pinjaman' => 'I',
            'id_pinkel' => 0,
            'nia' => 100,
            'jenis_pp' => 1,
            'alokasi' => '2000000',
            'pros_jasa' => '1.5',
            'jangka' => '12',
            'status' => 'A',
            'sistem_angsuran' => 0,
            'spk_no' => null,
            'tgl_proposal' => '2025-01-01',
            'tgl_verifikasi' => '2025-01-02',
            'tgl_dana' => '2025-01-03',
            'tgl_tunggu' => '2025-01-03',
            'tgl_cair' => '2025-01-05',
            'tgl_lunas' => null,
            'catatan_verifikasi' => 'OK',
            'catatan_bimbingan' => null,
            'waktu_verifikasi' => null,
            'wt_cair' => null,
        ]);

        Schema::connection('tenant')->create('rencana_angsuran_i_99', function ($table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->default(0);
            $table->string('angsuran_ke', 3)->default('0');
            $table->date('jatuh_tempo')->nullable();
            $table->bigInteger('wajib_pokok')->default(0);
            $table->bigInteger('wajib_jasa')->default(0);
        });
        $db->table('rencana_angsuran_i_99')->insert([
            'id' => 1,
            'loan_id' => 2,
            'angsuran_ke' => '1',
            'jatuh_tempo' => '2025-02-05',
            'wajib_pokok' => 100000,
            'wajib_jasa' => 5000,
        ]);

        Schema::connection('tenant')->create('real_angsuran_i_99', function ($table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->default(0);
            $table->date('tgl_transaksi')->nullable();
            $table->bigInteger('realisasi_pokok')->default(0);
            $table->bigInteger('realisasi_jasa')->default(0);
        });
        $db->table('real_angsuran_i_99')->insert([
            'id' => 1,
            'loan_id' => 2,
            'tgl_transaksi' => '2025-02-05',
            'realisasi_pokok' => 100000,
            'realisasi_jasa' => 5000,
        ]);

        // Empty group tables keep the shared reconciliation scopes green while
        // this test focuses on the individual-loan path.
        Schema::connection('tenant')->create('pinjaman_kelompok_99', function ($table): void {
            $table->bigIncrements('id');
            $table->string('alokasi', 100)->default('0');
            $table->string('status', 2)->default('A');
            $table->date('tgl_lunas')->nullable();
            $table->date('tgl_cair')->nullable();
        });

        Schema::connection('tenant')->create('rencana_angsuran_99', function ($table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->default(0);
            $table->string('angsuran_ke', 3)->default('0');
            $table->date('jatuh_tempo')->nullable();
            $table->bigInteger('wajib_pokok')->default(0);
            $table->bigInteger('wajib_jasa')->default(0);
        });

        Schema::connection('tenant')->create('real_angsuran_99', function ($table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->default(0);
            $table->date('tgl_transaksi')->nullable();
            $table->bigInteger('realisasi_pokok')->default(0);
            $table->bigInteger('realisasi_jasa')->default(0);
            $table->bigInteger('saldo_pokok')->default(0);
            $table->bigInteger('saldo_jasa')->default(0);
        });
    }

    private function memberRowId(): int
    {
        return (int) DB::connection('tenant')->table('members')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('id', 100)
            ->value('row_id');
    }
}
