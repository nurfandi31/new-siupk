<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantDataPurifierTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $superadmin;

    private Account $cash;

    private Account $equity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => null,
            'name' => 'Platform Superadmin',
            'email' => 'superadmin@example.test',
            'username' => 'superadmin_test',
            'password' => 'password',
            'is_superadmin' => true,
            'status' => 'active',
        ]);

        $this->cash = Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Kas',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01',
            'name' => 'Modal',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        // Insert an opening balance
        DB::connection('tenant')->table('account_opening_balances')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => 1,
            'account_row_id' => $this->cash->row_id,
            'fiscal_year' => 2026,
            'debit' => 5000000.00,
            'credit' => 0.00,
            'source' => 'migration',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_superadmin_can_view_data_purifier_index(): void
    {
        $entry = $this->createManualEntry(100000, 'Transaksi Training 1');

        $this->actingAs($this->superadmin)
            ->get('/admin/tenants/'.$this->testTenant->row_id.'/data-purifier')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/DataPurifier/Index')
                ->has('rows', 1)
                ->where('rows.0.id', $entry->id)
                ->has('stats')
            );
    }

    public function test_superadmin_can_start_training_session(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/tenants/'.$this->testTenant->row_id.'/data-purifier/start-training')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->testTenant->refresh();
        self::assertTrue($this->testTenant->isTraining());
        self::assertNotNull($this->testTenant->training_started_at);
        self::assertNull($this->testTenant->training_ended_at);
    }

    public function test_superadmin_can_selectively_purge_transactions(): void
    {
        $entry1 = $this->createManualEntry(10000, 'Transaksi Salah 1');
        $entry2 = $this->createManualEntry(20000, 'Transaksi Salah 2');
        $entry3 = $this->createManualEntry(30000, 'Transaksi Benar 3 (Keep)');

        $this->actingAs($this->superadmin)
            ->post('/admin/tenants/'.$this->testTenant->row_id.'/data-purifier/purge', [
                'entry_ids' => [$entry1->row_id, $entry2->row_id],
                'include_reversal_pairs' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Entry 1 and 2 are permanently deleted
        self::assertFalse(JournalEntry::query()->where('row_id', $entry1->row_id)->exists());
        self::assertFalse(JournalEntry::query()->where('row_id', $entry2->row_id)->exists());
        self::assertFalse(JournalLine::query()->where('journal_entry_row_id', $entry1->row_id)->exists());
        self::assertFalse(JournalLine::query()->where('journal_entry_row_id', $entry2->row_id)->exists());

        // Entry 3 is preserved!
        self::assertTrue(JournalEntry::query()->where('row_id', $entry3->row_id)->exists());

        // Opening balance is completely untouched!
        self::assertTrue(
            DB::connection('tenant')
                ->table('account_opening_balances')
                ->where('tenant_id', $this->testTenant->row_id)
                ->where('account_row_id', $this->cash->row_id)
                ->exists()
        );
    }

    public function test_superadmin_can_reset_training_transactions_within_time_boundary(): void
    {
        // 1 Pre-training entry created 2 days ago
        $preEntry = $this->createManualEntry(50000, 'Saldo Awal Transaksi', '2026-07-01');
        DB::connection('tenant')->table('journal_entries')->where('row_id', $preEntry->row_id)->update([
            'created_at' => now()->subDays(2),
        ]);

        // Start training 1 hour ago
        $this->testTenant->forceFill([
            'is_training_mode' => true,
            'training_started_at' => now()->subHour(),
        ])->save();

        // 2 Training entries created during training session
        $training1 = $this->createManualEntry(15000, 'Training 1');
        $training2 = $this->createManualEntry(25000, 'Training 2');

        $this->actingAs($this->superadmin)
            ->post('/admin/tenants/'.$this->testTenant->row_id.'/data-purifier/reset-training')
            ->assertRedirect()
            ->assertSessionHas('success');

        // Training entries are deleted
        self::assertFalse(JournalEntry::query()->where('row_id', $training1->row_id)->exists());
        self::assertFalse(JournalEntry::query()->where('row_id', $training2->row_id)->exists());

        // Pre-training entry is preserved!
        self::assertTrue(JournalEntry::query()->where('row_id', $preEntry->row_id)->exists());

        // Opening balance is preserved!
        self::assertSame(
            1,
            DB::connection('tenant')
                ->table('account_opening_balances')
                ->where('tenant_id', $this->testTenant->row_id)
                ->count()
        );
    }

    public function test_superadmin_can_end_training_and_go_live(): void
    {
        $this->testTenant->forceFill([
            'is_training_mode' => true,
            'training_started_at' => now()->subHours(2),
        ])->save();

        $trainingEntry = $this->createManualEntry(15000, 'Training Data');

        // End training with purge
        $this->actingAs($this->superadmin)
            ->post('/admin/tenants/'.$this->testTenant->row_id.'/data-purifier/end-training', [
                'purge_data' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->testTenant->refresh();
        self::assertFalse($this->testTenant->isTraining());
        self::assertNotNull($this->testTenant->training_ended_at);
        self::assertTrue($this->testTenant->hasCompletedTraining());

        // Training data is purged
        self::assertFalse(JournalEntry::query()->where('row_id', $trainingEntry->row_id)->exists());
    }

    private function createManualEntry(float $amount, string $description, string $date = '2026-07-18'): JournalEntry
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => $date,
            'sequence_number' => 1,
            'source_type' => 'manual',
            'description' => $description,
            'status' => 'draft',
            'created_by_user_id' => $this->superadmin->row_id,
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'debit' => number_format($amount, 2, '.', ''),
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->equity->row_id,
            'debit' => '0.00',
            'credit' => number_format($amount, 2, '.', ''),
        ]);

        return app(JournalPostingService::class)->post($entry, (int) $this->superadmin->row_id);
    }
}
