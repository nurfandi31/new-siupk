<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\InstallmentReceiptService;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use DomainException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class InstallmentReceiptTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private int $productId;

    private Account $cash;

    private Account $receivable;

    private Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Receipt',
            'email' => 'receipt@example.test',
            'username' => 'receipt_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Receipt',
            'type' => 'village',
            'is_active' => true,
        ]);

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'LKD Uji Receipt',
            'short_name' => 'LKD Receipt',
            'address' => 'Jl. Uji No. 1',
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $this->cash = Account::query()->create([
            'code' => '1.1.01.01',
            'name' => 'Kas',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->receivable = Account::query()->create([
            'code' => '1.1.03.01',
            'name' => 'Piutang SPP',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->revenue = Account::query()->create([
            'code' => '4.1.01.01',
            'name' => 'Pendapatan Jasa SPP',
            'account_type' => 'revenue',
            'normal_balance' => 'C',
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

    public function test_receipt_builds_amounts_from_posted_installment_journal(): void
    {
        $entry = $this->postedInstallmentJournal();

        $data = app(InstallmentReceiptService::class)->build($entry);

        self::assertSame('LKD Uji Receipt', $data['identity']['legal_name']);
        self::assertEqualsWithDelta(100000.0, $data['amounts']['principal'], 0.01);
        self::assertEqualsWithDelta(15000.0, $data['amounts']['interest'], 0.01);
        self::assertEqualsWithDelta(0.0, $data['amounts']['penalty'], 0.01);
        self::assertEqualsWithDelta(115000.0, $data['amounts']['total'], 0.01);
        self::assertSame('Kelompok Receipt', $data['loan']['group_name']);
        self::assertSame('Siti Penyetor', $data['payer']['name']);
        self::assertCount(3, $data['lines']);
    }

    public function test_receipt_rejects_non_installment_journal(): void
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'description' => 'Jurnal umum',
            'status' => 'draft',
            'source_type' => 'manual',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'debit' => '50000.00',
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->revenue->row_id,
            'debit' => '0.00',
            'credit' => '50000.00',
        ]);
        $posted = app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);

        $this->expectException(DomainException::class);
        app(InstallmentReceiptService::class)->build($posted);
    }

    public function test_receipt_accepts_legacy_angs_journal(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-LEGR',
            'name' => 'Kelompok Legacy Receipt',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 9,
            'loan_number' => 'PK-LEG-9',
            'proposed_at' => '2026-01-01',
            'disbursed_at' => '2026-01-15',
            'principal_amount' => 5000000,
            'interest_rate' => 1.5,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);
        // Force local id like legacy pinjaman id
        DB::connection('tenant')->table('loans')->where('row_id', $loan->row_id)->update(['id' => 8163]);
        $loan->id = 8163;

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $group->row_id,
            'member_row_id' => null,
        ]);

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-17',
            'sequence_number' => 10,
            'source_type' => 'legacy_transaksi',
            'legacy_loan_id' => 8163,
            'description' => 'Angs. (P) TES WA GETWAY (8163) [Pamutuh]',
            'status' => 'draft',
            'created_by_user_id' => $this->user->row_id,
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'description' => 'Kas',
            'debit' => '100000.00',
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->receivable->row_id,
            'description' => 'Piutang',
            'debit' => '0.00',
            'credit' => '100000.00',
        ]);
        $posted = app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);

        $data = app(InstallmentReceiptService::class)->build($posted);

        self::assertEqualsWithDelta(100000.0, $data['amounts']['principal'], 0.01);
        self::assertEqualsWithDelta(0.0, $data['amounts']['interest'], 0.01);
        self::assertNotNull($data['loan']);
        self::assertSame(8163, $data['loan']['id']);
        self::assertSame('Kelompok Legacy Receipt', $data['loan']['group_name']);
        self::assertSame('TES WA GETWAY', $data['payer']['name']);
    }

    public function test_receipt_pdf_route_streams(): void
    {
        $entry = $this->postedInstallmentJournal();

        $this->actingAs($this->user)
            ->get('/accounting/journal-entries/'.$entry->row_id.'/installment-receipt')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function postedInstallmentJournal(): JournalEntry
    {
        $group = Group::query()->create([
            'code' => 'KLP-RCPT',
            'name' => 'Kelompok Receipt',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);

        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 1,
            'loan_number' => 'PK-RCPT-1',
            'proposed_at' => '2026-01-01',
            'disbursed_at' => '2026-01-15',
            'principal_amount' => 1000000,
            'interest_rate' => 1.5,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $group->row_id,
            'member_row_id' => null,
        ]);

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'source_type' => 'loan_installment',
            'source_row_id' => $loan->row_id,
            'transaction_type' => 'angsuran',
            'description' => 'Angsuran SPP ke-1 Kelompok Kelompok Receipt a/n Siti Penyetor. Rincian: Pokok Rp 100.000, Jasa Rp 15.000',
            'status' => 'draft',
            'created_by_user_id' => $this->user->row_id,
        ]);

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'description' => 'Kas/Bank sumber dana',
            'debit' => '115000.00',
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->receivable->row_id,
            'description' => 'Piutang pinjaman',
            'debit' => '0.00',
            'credit' => '100000.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 3,
            'account_row_id' => $this->revenue->row_id,
            'description' => 'Pendapatan jasa',
            'debit' => '0.00',
            'credit' => '15000.00',
        ]);

        return app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }
}
