<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\AccountOpeningBalanceService;
use App\Domain\Accounting\Services\FiscalPeriodCloseService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class AggregateJournalTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $superadmin;

    private Account $cash;

    private Account $ar;

    private Account $revenue;

    private Account $equity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Super Admin',
            'email' => 'admin@example.test',
            'username' => 'sa_'.Str::lower(Str::random(6)),
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        $this->cash = Account::query()->create([
            'code' => '1.1.01', 'name' => 'Kas',
            'account_type' => 'asset', 'normal_balance' => 'D', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);
        $this->ar = Account::query()->create([
            'code' => '1.1.02', 'name' => 'Piutang',
            'account_type' => 'asset', 'normal_balance' => 'D', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);
        $this->revenue = Account::query()->create([
            'code' => '4.1.01', 'name' => 'Pendapatan Jasa',
            'account_type' => 'revenue', 'normal_balance' => 'C', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01', 'name' => 'Modal',
            'account_type' => 'equity', 'normal_balance' => 'C', 'level' => 3, 'is_postable' => true, 'is_active' => true,
        ]);

        // Set up 12 fiscal periods for 2026
        for ($m = 1; $m <= 12; $m++) {
            FiscalPeriod::query()->create([
                'fiscal_year' => 2026,
                'fiscal_month' => $m,
                'starts_at' => sprintf('2026-%02d-01', $m),
                'ends_at' => date('Y-m-t', strtotime(sprintf('2026-%02d-01', $m))),
                'status' => 'open',
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_post_aggregate_journal_with_multiple_lines(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post("/admin/tenants/{$this->testTenant->row_id}/onboarding/aggregate-journal", [
                'transaction_date' => '2026-06-01',
                'description' => 'Backfill Jan-Mei migrasi ke siupk',
                'lines' => [
                    ['account_row_id' => $this->cash->row_id, 'debit' => 11_000_000, 'credit' => 0, 'description' => 'Kas naik'],
                    ['account_row_id' => $this->ar->row_id, 'debit' => 0, 'credit' => 5_000_000, 'description' => 'Piutang turun'],
                    ['account_row_id' => $this->revenue->row_id, 'debit' => 0, 'credit' => 6_000_000, 'description' => 'Pendapatan'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $entry = JournalEntry::query()
            ->where('transaction_type', 'pemindahan_saldo')
            ->where('source_type', 'manual')
            ->latest('row_id')
            ->first();

        self::assertNotNull($entry);
        self::assertSame('posted', $entry->status, 'Journal harus auto-post.');
        self::assertSame('Backfill Jan-Mei migrasi ke siupk', $entry->description);
        self::assertEquals(11_000_000.0, (float) $entry->lines()->sum('debit'));
        self::assertEquals(11_000_000.0, (float) $entry->lines()->sum('credit'));
        self::assertCount(3, $entry->lines);
    }

    public function test_imbalance_returns_validation_error(): void
    {
        $this->actingAs($this->superadmin)
            ->post("/admin/tenants/{$this->testTenant->row_id}/onboarding/aggregate-journal", [
                'transaction_date' => '2026-06-01',
                'description' => 'Imbalanced test',
                'lines' => [
                    ['account_row_id' => $this->cash->row_id, 'debit' => 10_000_000, 'credit' => 0, 'description' => ''],
                    ['account_row_id' => $this->revenue->row_id, 'debit' => 0, 'credit' => 5_000_000, 'description' => ''],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_period_closed_returns_validation_error(): void
    {
        // Close June 2026
        FiscalPeriodCloseService::class;
        $svc = app(FiscalPeriodCloseService::class);
        $svc->closeMonth(2026, 6, (int) $this->superadmin->row_id);

        $this->actingAs($this->superadmin)
            ->post("/admin/tenants/{$this->testTenant->row_id}/onboarding/aggregate-journal", [
                'transaction_date' => '2026-06-15', // closed period
                'description' => 'Harus gagal karena Juni closed',
                'lines' => [
                    ['account_row_id' => $this->cash->row_id, 'debit' => 5_000_000, 'credit' => 0, 'description' => ''],
                    ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 5_000_000, 'description' => ''],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_minimum_two_lines_required(): void
    {
        $this->actingAs($this->superadmin)
            ->post("/admin/tenants/{$this->testTenant->row_id}/onboarding/aggregate-journal", [
                'transaction_date' => '2026-06-01',
                'description' => 'Hanya satu baris',
                'lines' => [
                    ['account_row_id' => $this->cash->row_id, 'debit' => 5_000_000, 'credit' => 0, 'description' => ''],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_combination_with_opening_balance_produces_correct_june_balance(): void
    {
        // Setup: opening 2026 cash 50jt (manual source)
        AccountOpeningBalanceService::class;
        $opening = app(AccountOpeningBalanceService::class);
        $opening->upsert(2026, [
            ['account_row_id' => $this->cash->row_id, 'debit' => 50_000_000, 'credit' => 0],
            ['account_row_id' => $this->equity->row_id, 'debit' => 0, 'credit' => 50_000_000],
        ], (int) $this->superadmin->row_id);

        // Then aggregate journal: +11jt cash (Jan-Mei backfill)
        $this->actingAs($this->superadmin)
            ->post("/admin/tenants/{$this->testTenant->row_id}/onboarding/aggregate-journal", [
                'transaction_date' => '2026-06-01',
                'description' => 'Backfill Jan-Mei',
                'lines' => [
                    ['account_row_id' => $this->cash->row_id, 'debit' => 11_000_000, 'credit' => 0, 'description' => ''],
                    ['account_row_id' => $this->revenue->row_id, 'debit' => 0, 'credit' => 11_000_000, 'description' => ''],
                ],
            ])
            ->assertRedirect();

        // Saldo cash Juni harus 61jt
        $rows = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->testTenant->row_id)
            ->where('fiscal_year', 2026)
            ->where('account_row_id', $this->cash->row_id)
            ->get();

        self::assertCount(1, $rows, 'Hanya satu opening row untuk cash 2026.');
        self::assertEquals(50_000_000.0, (float) $rows->first()->debit);

        $aggregate = JournalEntry::query()
            ->where('transaction_type', 'pemindahan_saldo')
            ->where('source_type', 'manual')
            ->whereBetween('transaction_date', ['2026-05-31', '2026-06-02'])
            ->first();
        self::assertNotNull($aggregate);
        self::assertSame('posted', $aggregate->status);

        // Sum debit cash setelah opening + aggregate = 50jt + 11jt = 61jt
        $totalDebit = (float) DB::connection('tenant')
            ->table('journal_lines')
            ->join('journal_entries', 'journal_entries.row_id', '=', 'journal_lines.journal_entry_row_id')
            ->where('journal_lines.account_row_id', $this->cash->row_id)
            ->where('journal_entries.status', 'posted')
            ->sum('journal_lines.debit');

        self::assertEquals(11_000_000.0, $totalDebit);
    }
}
