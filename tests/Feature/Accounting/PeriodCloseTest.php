<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\FiscalPeriodCloseService;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantSequenceService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class PeriodCloseTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Account $cash;

    private Account $equity;

    private Account $revenue;

    private Account $expense;

    private Account $earnings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Tutup',
            'email' => 'close@example.test',
            'username' => 'close_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $accountCreatedAt = CarbonImmutable::parse('2025-01-01')->startOfDay();
        $this->cash = Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Kas',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01',
            'name' => 'Modal',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $this->earnings = Account::query()->create([
            'code' => '3.2.02.01',
            'name' => 'Laba/Rugi Tahun Berjalan',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $this->revenue = Account::query()->create([
            'code' => '4.1.01',
            'name' => 'Pendapatan Jasa',
            'account_type' => 'revenue',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);
        $this->expense = Account::query()->create([
            'code' => '5.1.01',
            'name' => 'Beban Operasional',
            'account_type' => 'expense',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $accountCreatedAt,
        ]);

        // Opening 2025: kas 1.000.000 / modal 1.000.000 (via sequence — same path as production)
        $sequences = app(TenantSequenceService::class);
        $now = now();
        DB::connection('tenant')->table('account_opening_balances')->insert([
            [
                'tenant_id' => $this->testTenant->row_id,
                'id' => $sequences->next('account_opening_balances'),
                'account_row_id' => $this->cash->row_id,
                'fiscal_year' => 2025,
                'debit' => 1000000,
                'credit' => 0,
                'source' => 'migration',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->testTenant->row_id,
                'id' => $sequences->next('account_opening_balances'),
                'account_row_id' => $this->equity->row_id,
                'fiscal_year' => 2025,
                'debit' => 0,
                'credit' => 1000000,
                'source' => 'migration',
                'created_at' => $now,
                'updated_at' => $now,
            ],
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

        // Income: debit kas 200k, credit revenue 200k; expense debit 50k credit kas 50k → NI 150k
        $this->postJournal('2025-06-15', [
            [$this->cash->row_id, 200000, 0],
            [$this->revenue->row_id, 0, 200000],
        ]);
        $this->postJournal('2025-06-20', [
            [$this->expense->row_id, 50000, 0],
            [$this->cash->row_id, 0, 50000],
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_index_renders_period_close(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/period-close?year=2025')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/PeriodClose/Index')
                ->where('year', 2025)
                ->has('months', 12)
                ->where('open_count', 12)
            );
    }

    public function test_index_includes_trial_balance_payload(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/period-close?year=2025')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/PeriodClose/Index')
                ->where('year', 2025)
                ->has('trial_balance')
                ->where('trial_balance.balanced', true)
                ->where('trial_balance.period.as_of', '2025-12-31')
                ->has('trial_balance.rows')
                ->has('trial_balance.totals.ns_debit')
                ->has('trial_balance.totals.ns_credit')
            );
    }

    public function test_trial_balance_uses_year_end_month(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/period-close?year=2025')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('trial_balance.period.as_of', '2025-12-31')
                ->where('trial_balance.period.year', 2025)
                ->where('trial_balance.period.month', 12)
            );
    }

    public function test_close_month_blocks_posting(): void
    {
        $service = app(FiscalPeriodCloseService::class);
        $service->closeMonth(2025, 6, (int) $this->user->row_id);

        $period = FiscalPeriod::query()
            ->where('fiscal_year', 2025)
            ->where('fiscal_month', 6)
            ->first();
        self::assertSame('closed', $period?->status);

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2025-06-25',
            'sequence_number' => 99,
            'description' => 'Should fail',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'debit' => 1000,
            'credit' => 0,
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->equity->row_id,
            'debit' => 0,
            'credit' => 1000,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The fiscal period is not open.');
        app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }

    public function test_close_year_writes_next_openings(): void
    {
        $result = app(FiscalPeriodCloseService::class)->closeYear(2025, (int) $this->user->row_id);

        self::assertSame(12, $result['closed_months']);
        self::assertSame(2026, $result['next_year']);
        self::assertEqualsWithDelta(150000.0, $result['net_income'], 0.01);

        $closed = FiscalPeriod::query()->where('fiscal_year', 2025)->where('status', 'closed')->count();
        self::assertSame(12, $closed);

        // Next year periods auto-created
        self::assertSame(12, FiscalPeriod::query()->where('fiscal_year', 2026)->count());

        $openings = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('source', 'year_close')
            ->get()
            ->keyBy('account_row_id');

        // Kas: 1_000_000 + 200_000 - 50_000 = 1_150_000 debit
        $cashOpen = $openings->get($this->cash->row_id);
        self::assertNotNull($cashOpen);
        self::assertEqualsWithDelta(1150000.0, (float) $cashOpen->debit, 0.01);
        self::assertEqualsWithDelta(0.0, (float) $cashOpen->credit, 0.01);

        // Modal stays 1_000_000 credit
        $equityOpen = $openings->get($this->equity->row_id);
        self::assertNotNull($equityOpen);
        self::assertEqualsWithDelta(1000000.0, (float) $equityOpen->credit, 0.01);

        // Earnings carries NI 150_000 credit
        $earnOpen = $openings->get($this->earnings->row_id);
        self::assertNotNull($earnOpen);
        self::assertEqualsWithDelta(150000.0, (float) $earnOpen->credit, 0.01);

        // Revenue/expense must NOT have openings
        self::assertNull($openings->get($this->revenue->row_id));
        self::assertNull($openings->get($this->expense->row_id));
    }

    public function test_close_month_rejects_drafts(): void
    {
        JournalEntry::query()->create([
            'transaction_date' => '2025-03-10',
            'sequence_number' => 50,
            'description' => 'Draft leftover',
            'status' => 'draft',
        ]);

        $this->expectException(DomainException::class);
        app(FiscalPeriodCloseService::class)->closeMonth(2025, 3, (int) $this->user->row_id);
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
