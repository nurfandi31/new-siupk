<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use DomainException;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class JournalPostingServiceTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_balanced_journal_can_be_posted(): void
    {
        [$cash, $equity] = $this->createAccounts();

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'description' => 'Opening capital',
            'status' => 'draft',
        ]);

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $cash->row_id,
            'debit' => '1000000.00',
            'credit' => '0.00',
        ]);

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $equity->row_id,
            'debit' => '0.00',
            'credit' => '1000000.00',
        ]);

        $posted = app(JournalPostingService::class)->post($entry, 1);

        self::assertSame('posted', $posted->status);
        self::assertNotNull($posted->posted_at);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        [$cash, $equity] = $this->createAccounts();

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 2,
            'description' => 'Invalid journal',
            'status' => 'draft',
        ]);

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $cash->row_id,
            'debit' => '100.00',
            'credit' => '0.00',
        ]);

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $equity->row_id,
            'debit' => '0.00',
            'credit' => '90.00',
        ]);

        $this->expectException(DomainException::class);
        app(JournalPostingService::class)->post($entry, 1);
    }

    /**
     * @return array{Account,Account}
     */
    private function createAccounts(): array
    {
        $cash = Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Cash',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $equity = Account::query()->create([
            'code' => '3.1.01',
            'name' => 'Capital',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);

        return [$cash, $equity];
    }
}
