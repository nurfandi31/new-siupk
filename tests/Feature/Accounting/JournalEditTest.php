<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalEditService;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\JournalReversalService;
use App\Domain\Assets\Models\Asset;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class JournalEditTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Account $cash;

    private Account $equity;

    private Account $assetAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Edit',
            'email' => 'edit@example.test',
            'username' => 'edit_user',
            'password' => 'password',
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
        $this->assetAccount = Account::query()->create([
            'code' => '1.2.01.04',
            'name' => 'Inventaris',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
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
        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 8,
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-31',
            'status' => 'open',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_edit_reverses_old_and_creates_posted_new_entry(): void
    {
        $original = $this->postedManual(100000.00, 'Jurnal yang akan diedit');

        $beforeCount = JournalEntry::query()->count();

        $response = $this->actingAs($this->user)
            ->put('/accounting/journals/'.$original->row_id, [
                'transaction_date' => '2026-07-18',
                'transaction_type' => 'aset_masuk',
                'description' => 'Koreksi nominal',
                'reference' => 'REF-001',
                'amount' => 150000,
                'sumber_dana_row_id' => $this->equity->row_id,
                'disimpan_ke_row_id' => $this->cash->row_id,
                'reason' => 'Nominal awal keliru',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        self::assertSame($beforeCount + 2, JournalEntry::query()->count());

        // Original entry masih ada tapi sudah ditandai "dibatalkan" (punya entry lain yang reverse-nya)
        $reversal = JournalEntry::query()
            ->where('reversed_entry_row_id', $original->row_id)
            ->first();
        self::assertNotNull($reversal);
        self::assertSame('posted', $reversal->status);
        self::assertSame('journal_reversal', $reversal->source_type);
        self::assertSame('Nominal awal keliru', $reversal->description);

        // Jurnal baru dipost dengan amount baru
        $newEntry = JournalEntry::query()
            ->where('description', 'like', '[Koreksi jurnal #%')
            ->orderByDesc('row_id')
            ->first();
        self::assertNotNull($newEntry);
        self::assertSame('posted', $newEntry->status);
        self::assertSame('manual', $newEntry->source_type);
        self::assertSame(150000.00, (float) $newEntry->lines->sum('debit'));

        $message = (string) session('success');
        self::assertStringContainsString('#'.$original->id, $message);
        self::assertStringContainsString('#'.$newEntry->id, $message);
    }

    public function test_edit_form_renders_with_prefill(): void
    {
        $original = $this->postedManual(75000.00, 'Sample edit prefill');

        $this->actingAs($this->user)
            ->get('/accounting/journals/'.$original->row_id.'/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/JournalEntries/Edit')
                ->where('originalEntry.id', $original->id)
                ->where('prefill.amount', 75000)
                ->where('prefill.transaction_type', 'aset_masuk')
                ->where('prefill.description', 'Sample edit prefill')
                ->where('prefill.transaction_date', '2026-07-18')
            );
    }

    public function test_edit_requires_reason(): void
    {
        $original = $this->postedManual(50000.00, 'Butuh alasan');

        $this->actingAs($this->user)
            ->put('/accounting/journals/'.$original->row_id, [
                'transaction_date' => '2026-07-18',
                'transaction_type' => 'aset_masuk',
                'description' => 'Tanpa alasan',
                'amount' => 50000,
                'sumber_dana_row_id' => $this->equity->row_id,
                'disimpan_ke_row_id' => $this->cash->row_id,
                // reason missing
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_edit_blocks_non_editable_source_type(): void
    {
        // Buat entry dengan source_type yang tidak boleh di-edit
        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'source_type' => 'journal_reversal', // bukan manual/asset_purchase
            'description' => 'Hasil reverse',
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_user_id' => (int) $this->user->row_id,
            'created_by_user_id' => (int) $this->user->row_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/accounting/journals/'.$entry->row_id.'/edit');
        $response->assertStatus(422);
    }

    public function test_edit_blocks_already_reversed(): void
    {
        $original = $this->postedManual(25000.00, 'Akan di-reverse dulu');

        // Reverse dulu
        app(JournalReversalService::class)
            ->reverse($original, '2026-07-19', (int) $this->user->row_id, 'Persiapan test');

        $this->actingAs($this->user)
            ->get('/accounting/journals/'.$original->row_id.'/edit')
            ->assertStatus(422);
    }

    public function test_edit_atomic_when_posting_new_fails(): void
    {
        $original = $this->postedManual(80000.00, 'Uji atomisitas');

        $beforeCount = JournalEntry::query()->count();

        // Bikin periode fiscal untuk transaction_date jadi closed
        FiscalPeriod::query()
            ->where('fiscal_year', 2026)
            ->where('fiscal_month', 7)
            ->update(['status' => 'closed']);

        // Coba edit dengan transaction_date di bulan yang closed → posting akan gagal
        // Tapi reversal seharusnya sudah terjadi... tunggu, kami tetap harus atomic, jadi dua-duanya harus rollback
        $this->actingAs($this->user)
            ->put('/accounting/journals/'.$original->row_id, [
                'transaction_date' => '2026-07-18',
                'transaction_type' => 'aset_masuk',
                'description' => 'Coba edit',
                'amount' => 90000,
                'sumber_dana_row_id' => $this->equity->row_id,
                'disimpan_ke_row_id' => $this->cash->row_id,
                'reason' => 'Tes rollback',
            ])
            ->assertSessionHas('error');

        // Atomic: tidak ada entry baru
        self::assertSame($beforeCount, JournalEntry::query()->count(), 'Edit harus atomic — tidak ada entry baru kalau posting gagal');

        // Original masih "posted" dan belum di-reverse
        $original->refresh();
        self::assertSame('posted', $original->status);
        self::assertNull($original->reversed_entry_row_id);
    }

    public function test_edit_handles_asset_purchase_with_new_asset(): void
    {
        $asset = Asset::query()->create([
            'name' => 'Laptop lama',
            'purchased_at' => '2026-07-18',
            'quantity' => 1,
            'unit_cost' => 5000000,
            'useful_life_months' => 48,
            'status' => 'good',
        ]);

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'source_type' => 'asset_purchase',
            'source_row_id' => $asset->row_id,
            'transaction_type' => 'pembelian_aset_peralatan',
            'description' => 'Pembelian laptop lama',
            'status' => 'draft',
            'created_by_user_id' => (int) $this->user->row_id,
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->assetAccount->row_id,
            'debit' => '5000000.00',
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->cash->row_id,
            'debit' => '0.00',
            'credit' => '5000000.00',
        ]);
        $original = app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);

        $assetCountBefore = Asset::query()->count();

        $this->actingAs($this->user)
            ->put('/accounting/journals/'.$original->row_id, [
                'transaction_date' => '2026-07-18',
                'transaction_type' => 'pembelian_aset_peralatan',
                'description' => 'Koreksi qty laptop',
                'amount' => 10000000,
                'sumber_dana_row_id' => $this->cash->row_id,
                'disimpan_ke_row_id' => $this->assetAccount->row_id,
                'asset_name' => 'Laptop baru',
                'asset_quantity' => 2,
                'asset_unit_cost' => 5000000,
                'asset_useful_life_months' => 48,
                'reason' => 'Qty salah',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Asset baru terdaftar
        self::assertSame($assetCountBefore + 1, Asset::query()->count());

        // Asset lama masih ada (tidak terhapus, terkait jurnal lama yang di-reverse)
        self::assertNotNull(Asset::query()->find($asset->row_id));

        // Jurnal baru memiliki source_row_id = asset baru
        $newEntry = JournalEntry::query()
            ->where('description', 'like', '[Koreksi jurnal #%')
            ->orderByDesc('row_id')
            ->firstOrFail();
        self::assertSame('asset_purchase', $newEntry->source_type);
        self::assertNotSame((int) $asset->row_id, (int) $newEntry->source_row_id);
    }

    public function test_edit_service_standalone_throws_for_non_editable(): void
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'source_type' => 'profit_allocation',
            'description' => 'Profit',
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_user_id' => (int) $this->user->row_id,
            'created_by_user_id' => (int) $this->user->row_id,
        ]);

        $this->expectException(\DomainException::class);

        app(JournalEditService::class)->edit(
            original: $entry,
            data: [
                'transaction_date' => '2026-07-18',
                'transaction_type' => 'aset_masuk',
                'description' => 'Coba',
                'amount' => 1,
                'sumber_dana_row_id' => $this->equity->row_id,
                'disimpan_ke_row_id' => $this->cash->row_id,
            ],
            reversalDate: '2026-07-18',
            reason: 'test',
            platformUserId: (int) $this->user->row_id,
        );
    }

    private function postedManual(float $amount, string $description): JournalEntry
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'source_type' => 'manual',
            'transaction_type' => 'aset_masuk',
            'description' => $description,
            'status' => 'draft',
            'created_by_user_id' => (int) $this->user->row_id,
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

        return app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }
}
