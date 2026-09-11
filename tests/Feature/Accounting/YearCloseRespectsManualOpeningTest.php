<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\AccountOpeningBalanceService;
use App\Domain\Accounting\Services\FiscalPeriodCloseService;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantSequenceService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class YearCloseRespectsManualOpeningTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Account $cash;

    private Account $equity;

    private Account $revenue;

    private Account $expense;

    private Account $earnings;

    private FiscalPeriodCloseService $closeService;

    private AccountOpeningBalanceService $openingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Year Close Test',
            'email' => 'close@example.test',
            'username' => 'yc_'.Str::lower(Str::random(6)),
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->cash = Account::query()->create([
            'code' => '1.1.01', 'name' => 'Kas',
            'account_type' => 'asset', 'normal_balance' => 'D', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01', 'name' => 'Modal',
            'account_type' => 'equity', 'normal_balance' => 'C', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);
        $this->revenue = Account::query()->create([
            'code' => '4.1.01', 'name' => 'Pendapatan',
            'account_type' => 'revenue', 'normal_balance' => 'C', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);
        $this->expense = Account::query()->create([
            'code' => '5.1.01', 'name' => 'Beban',
            'account_type' => 'expense', 'normal_balance' => 'D', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);
        $this->earnings = Account::query()->create([
            'code' => '3.2.02.01', 'name' => 'Laba/Rugi Tahun Berjalan',
            'account_type' => 'equity', 'normal_balance' => 'C', 'level' => 4, 'is_postable' => true, 'is_active' => true,
        ]);

        // Seed opening 2025 via service (year_close path)
        $sequences = app(TenantSequenceService::class);
        $now = now();
        DB::connection('tenant')->table('account_opening_balances')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => $sequences->next('account_opening_balances'),
            'account_row_id' => $this->cash->row_id,
            'fiscal_year' => 2025, 'debit' => 1_000_000, 'credit' => 0,
            'source' => 'year_close', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::connection('tenant')->table('account_opening_balances')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => $sequences->next('account_opening_balances'),
            'account_row_id' => $this->equity->row_id,
            'fiscal_year' => 2025, 'debit' => 0, 'credit' => 1_000_000,
            'source' => 'year_close', 'created_at' => $now, 'updated_at' => $now,
        ]);

        for ($m = 1; $m <= 12; $m++) {
            FiscalPeriod::query()->create([
                'fiscal_year' => 2025,
                'fiscal_month' => $m,
                'starts_at' => sprintf('2025-%02d-01', $m),
                'ends_at' => date('Y-m-t', strtotime(sprintf('2025-%02d-01', $m))),
                'status' => 'open',
            ]);
        }

        // Some 2025 activity so closeYear akan membuat opening 2026
        $this->postJournal('2025-06-15', [
            [$this->cash->row_id, 200_000, 0],
            [$this->revenue->row_id, 0, 200_000],
        ]);

        $this->closeService = app(FiscalPeriodCloseService::class);
        $this->openingService = app(AccountOpeningBalanceService::class);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_year_close_does_not_overwrite_manual_opening(): void
    {
        // Admin input manual opening untuk akun revenue tahun 2026 (sebelum tutup buku 2025)
        $this->openingService->upsert(2026, [
            ['account_row_id' => $this->revenue->row_id, 'debit' => 0, 'credit' => 99_999],
            ['account_row_id' => $this->equity->row_id, 'debit' => 99_999, 'credit' => 0],
        ], (int) $this->user->row_id);

        // Jalankan tutup buku 2025
        $this->closeService->closeYear(2025, (int) $this->user->row_id);

        // Row manual revenue 2026 HARUS TETAP
        $row = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->revenue->row_id)
            ->first();

        self::assertNotNull($row);
        self::assertSame('manual', $row->source, 'Tutup buku tidak boleh overwrite source=manual.');
        self::assertEquals(99_999.0, (float) $row->credit, 'Nilai manual harus tetap.');
    }

    public function test_year_close_skips_migration_source_too(): void
    {
        // Migration opening untuk akun baru yang belum ada di year_close
        $sequences = app(TenantSequenceService::class);
        $now = now();
        DB::connection('tenant')->table('account_opening_balances')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => $sequences->next('account_opening_balances'),
            'account_row_id' => $this->revenue->row_id,
            'fiscal_year' => 2026, 'debit' => 0, 'credit' => 33_333,
            'source' => 'migration', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->closeService->closeYear(2025, (int) $this->user->row_id);

        $row = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->revenue->row_id)
            ->first();

        self::assertSame('migration', $row->source, 'Tutup buku tidak boleh overwrite source=migration.');
        self::assertEquals(33_333.0, (float) $row->credit, 'Nilai migration harus tetap.');
    }

    public function test_year_close_writes_new_year_close_rows_for_accounts_without_opening(): void
    {
        // Untuk akun tanpa opening awal (sebelum tutup buku), tutup buku akan tulis year_close row.
        $this->closeService->closeYear(2025, (int) $this->user->row_id);

        $rows = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('source', 'year_close')
            ->get()
            ->keyBy('account_row_id');

        // cash: 1_000_000 + 200_000 = 1_200_000
        self::assertEquals(1_200_000.0, (float) ($rows->get($this->cash->row_id)?->debit ?? 0));
        // earnings: NI credit 200_000
        self::assertEquals(200_000.0, (float) ($rows->get($this->earnings->row_id)?->credit ?? 0));
        // revenue/expense: NO year_close opening
        self::assertNull($rows->get($this->revenue->row_id));
        self::assertNull($rows->get($this->expense->row_id));
    }

    /**
     * @param  list<array{0: int, 1: float|int, 2: float|int}>  $lines
     */
    private function postJournal(string $date, array $lines): void
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => $date,
            'sequence_number' => random_int(1, 99999),
            'description' => 'Test '.$date,
            'status' => 'draft',
        ]);

        $n = 1;
        foreach ($lines as [$accountRowId, $debit, $credit]) {
            JournalLine::query()->create([
                'journal_entry_row_id' => $entry->row_id,
                'line_number' => $n++,
                'account_row_id' => $accountRowId,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }
}
