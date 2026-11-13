<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\Reports\CashEvidenceService;
use App\Domain\Accounting\Services\Reports\JournalKuitansiService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class JournalKuitansiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Account $cash;

    private Account $revenue;

    private Account $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Kuitansi',
            'email' => 'kuitansi@example.test',
            'username' => 'kuitansi_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'LKD Uji Kuitansi',
            'short_name' => 'LKD Kuitansi',
            'address' => 'Jl. Uji Kuitansi No. 1',
            'district_name' => 'Uji',
            'regency_name' => 'Kabupaten Uji',
            'phone' => '021000000',
        ]);

        $this->cash = Account::query()->create([
            'code' => '1.1.01.01',
            'name' => 'Kas',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->revenue = Account::query()->create([
            'code' => '4.1.01.01',
            'name' => 'Pendapatan Jasa',
            'account_type' => 'revenue',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->expense = Account::query()->create([
            'code' => '5.1.01.01',
            'name' => 'Beban Operasional',
            'account_type' => 'expense',
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
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_kuitansi_bkm_uses_cash_in_relation_and_officer(): void
    {
        $entry = $this->postedJournal('BKM', 125000, 'Siti Penyetor', 'Setoran simpanan anggota');

        $data = app(JournalKuitansiService::class)->build($entry, $this->user);

        self::assertSame('BKM', $data['kind']);
        self::assertSame('Siti Penyetor', $data['received_from']);
        self::assertSame('Siti Penyetor', $data['paid_by']);
        self::assertSame('Kasir Kuitansi', $data['received_by']);
        self::assertEqualsWithDelta(125000.0, $data['amount'], 0.01);
        self::assertStringContainsString('Seratus Dua Puluh Lima Ribu Rupiah', $data['amount_terbilang']);
        self::assertSame('18 Juli 2026', $data['document_date_label']);
        self::assertStringEndsWith('/BKM', $data['document_number'].'/'.$data['kind']);
    }

    public function test_kuitansi_bkk_uses_officer_as_payer_and_relation_as_recipient(): void
    {
        $entry = $this->postedJournal('BKK', 75000, 'Budi Penerima', 'Pembayaran beban operasional');

        $data = app(JournalKuitansiService::class)->build($entry, $this->user);

        self::assertSame('BKK', $data['kind']);
        self::assertSame('LKD Uji Kuitansi', $data['received_from']);
        self::assertSame('Kasir Kuitansi', $data['paid_by']);
        self::assertSame('Budi Penerima', $data['received_by']);
        self::assertEqualsWithDelta(75000.0, $data['amount'], 0.01);
    }

    public function test_kuitansi_matches_cash_evidence_kind_and_amount(): void
    {
        $bkmEntry = $this->postedJournal('BKM', 125000, 'Siti Penyetor', 'Setoran simpanan anggota');
        $bkkEntry = $this->postedJournal('BKK', 75000, 'Budi Penerima', 'Pembayaran beban operasional');

        $cashEvidence = app(CashEvidenceService::class);
        $kuitansi = app(JournalKuitansiService::class);

        foreach ([$bkmEntry, $bkkEntry] as $entry) {
            $cash = $cashEvidence->build($entry);
            $receipt = $kuitansi->build($entry, $this->user);

            self::assertSame($cash['kind'], $receipt['kind']);
            self::assertSame($cash['amount'], $receipt['amount']);
            self::assertSame($cash['document_number'], $receipt['document_number']);
            self::assertSame($cash['document_date'], $receipt['document_date']);
        }
    }

    public function test_kuitansi_pdf_route_streams(): void
    {
        $entry = $this->postedJournal('BKM', 125000, 'Siti Penyetor', 'Setoran simpanan anggota');

        $this->actingAs($this->user)
            ->get('/accounting/journals/'.$entry->row_id.'/kuitansi')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_kuitansi_thermal_route_streams(): void
    {
        $entry = $this->postedJournal('BKM', 125000, 'Siti Penyetor', 'Setoran simpanan anggota');

        $this->actingAs($this->user)
            ->get('/accounting/journals/'.$entry->row_id.'/kuitansi/thermal?kertas=58')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_journal_browse_exposes_kuitansi_url_for_general_journal(): void
    {
        $entry = $this->postedJournal('BKM', 125000, 'Siti Penyetor', 'Setoran simpanan anggota');

        $this->actingAs($this->user)
            ->get('/accounting/journals?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/Journals/Index')
                ->has('rows', 1)
                ->where('rows.0.kuitansi_url', '/accounting/journals/'.$entry->row_id.'/kuitansi')
                ->where('rows.0.kuitansi_thermal_url', '/accounting/journals/'.$entry->row_id.'/kuitansi/thermal')
                ->where('rows.0.receipt_url', null)
            );
    }

    private function postedJournal(string $kind, float $amount, string $relation, string $description): JournalEntry
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'source_type' => 'manual',
            'legacy_relation' => $relation,
            'description' => $description,
            'status' => 'draft',
            'created_by_user_id' => $this->user->row_id,
        ]);

        $debitAccount = $kind === 'BKK' ? $this->expense : $this->cash;
        $creditAccount = $kind === 'BKK' ? $this->cash : $this->revenue;

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $debitAccount->row_id,
            'debit' => number_format($amount, 2, '.', ''),
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $creditAccount->row_id,
            'debit' => '0.00',
            'credit' => number_format($amount, 2, '.', ''),
        ]);

        return app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }
}
