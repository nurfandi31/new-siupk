<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\ProfitAllocationService;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use DomainException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class ProfitAllocationTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Account $cash;

    private Account $earnings;

    private Account $retained;

    private Account $community;

    private Account $villageAcc;

    private Account $investor;

    private Account $revenue;

    private OrganizationUnit $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Alokasi',
            'email' => 'alloc@example.test',
            'username' => 'alloc_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->cash = $this->account('1.1.01', 'Kas', 'asset', 'D');
        $this->community = $this->account('2.1.04.01', 'Utang Laba Masyarakat', 'liability', 'C');
        $this->villageAcc = $this->account('2.1.04.02', 'Utang Laba Desa', 'liability', 'C');
        $this->investor = $this->account('2.1.04.03', 'Utang Laba Penyerta', 'liability', 'C');
        $this->retained = $this->account('3.2.01.01', 'Laba Ditahan', 'equity', 'C');
        $this->earnings = $this->account('3.2.02.01', 'Laba Berjalan', 'equity', 'C');
        $this->revenue = $this->account('4.1.01', 'Pendapatan', 'revenue', 'C');

        $this->village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Uji',
            'type' => 'village',
            'is_active' => true,
        ]);

        // Open 2025 + Jan 2026 (allocation date)
        for ($m = 1; $m <= 12; $m++) {
            FiscalPeriod::query()->create([
                'fiscal_year' => 2025,
                'fiscal_month' => $m,
                'starts_at' => sprintf('2025-%02d-01', $m),
                'ends_at' => date('Y-m-t', strtotime(sprintf('2025-%02d-01', $m))),
                'status' => 'open',
            ]);
        }
        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 1,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-01-31',
            'status' => 'open',
        ]);

        // Revenue 500_000 → NI 500_000
        $this->seedPostedJournal('2025-06-10', [
            [$this->cash->row_id, 500000, 0],
            [$this->revenue->row_id, 0, 500000],
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_allocate_posts_balanced_journal(): void
    {
        $result = app(ProfitAllocationService::class)->allocate(2025, [
            'date' => '2026-01-01',
            'community' => ['sosial' => 50000, 'kapasitas' => 25000, 'pelatihan' => 0],
            'villages' => [$this->village->row_id => 100000],
            'investor' => 50000,
            'retained' => 275000,
        ], (int) $this->user->row_id);

        self::assertEqualsWithDelta(500000.0, $result['total'], 0.01);

        $entry = JournalEntry::query()->whereKey($result['journal_row_id'])->first();
        self::assertNotNull($entry);
        self::assertSame('posted', $entry->status);
        self::assertSame('profit_allocation', $entry->source_type);
        self::assertSame(2025, (int) $entry->source_row_id);

        $lines = JournalLine::query()
            ->where('journal_entry_row_id', $entry->row_id)
            ->orderBy('line_number')
            ->get();

        $debit = round((float) $lines->sum('debit'), 2);
        $credit = round((float) $lines->sum('credit'), 2);
        self::assertEqualsWithDelta($debit, $credit, 0.01);
        self::assertEqualsWithDelta(500000.0, $debit, 0.01);

        // Earnings debited
        $earnLine = $lines->firstWhere('account_row_id', $this->earnings->row_id);
        self::assertEqualsWithDelta(500000.0, (float) $earnLine->debit, 0.01);

        // Village line tagged with org unit
        $vilLine = $lines->first(
            fn ($l) => (int) $l->account_row_id === (int) $this->villageAcc->row_id
        );
        self::assertNotNull($vilLine);
        self::assertSame((int) $this->village->row_id, (int) $vilLine->organization_unit_row_id);
        self::assertEqualsWithDelta(100000.0, (float) $vilLine->credit, 0.01);

        $state = app(ProfitAllocationService::class)->formState(2025);
        self::assertEqualsWithDelta(500000.0, $state['already_allocated'], 0.01);
        self::assertEqualsWithDelta(0.0, $state['remaining'], 0.01);
    }

    public function test_allocate_rejects_over_remaining(): void
    {
        $this->expectException(DomainException::class);
        app(ProfitAllocationService::class)->allocate(2025, [
            'date' => '2026-01-01',
            'retained' => 600000,
        ], (int) $this->user->row_id);
    }

    public function test_index_includes_allocation_payload(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/period-close?year=2025')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/PeriodClose/Index')
                ->where('allocation.available', 500000)
                ->where('allocation.remaining', 500000)
                ->has('allocation.villages', 1)
            );
    }

    private function account(string $code, string $name, string $type, string $normal): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $normal,
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array{0:int,1:float|int,2:float|int}>  $lines
     */
    private function seedPostedJournal(string $date, array $lines): void
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => $date,
            'sequence_number' => random_int(1, 99999),
            'description' => 'seed '.$date,
            'status' => 'draft',
        ]);
        $n = 1;
        foreach ($lines as [$acc, $d, $c]) {
            JournalLine::query()->create([
                'journal_entry_row_id' => $entry->row_id,
                'line_number' => $n++,
                'account_row_id' => $acc,
                'debit' => $d,
                'credit' => $c,
            ]);
        }
        app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }
}
