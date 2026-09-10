<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final readonly class JournalReversalService
{
    public function __construct(
        private JournalPostingService $postingService,
    ) {}

    public function reverse(
        JournalEntry $original,
        \DateTimeInterface|string $reversalDate,
        int $platformUserId,
        ?string $reason = null,
    ): JournalEntry {
        if ($original->status !== 'posted') {
            throw new DomainException('Only a posted journal may be reversed.');
        }

        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');

        $reversal = DB::connection($connectionName)->transaction(
            function (ConnectionInterface $connection) use ($original, $reversalDate, $platformUserId, $reason): JournalEntry {
                /** @var JournalEntry $lockedOriginal */
                $lockedOriginal = JournalEntry::query()
                    ->with('lines')
                    ->whereKey($original->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedOriginal->status !== 'posted') {
                    throw new DomainException('The journal is no longer available for reversal.');
                }

                if (JournalEntry::query()->where('reversed_entry_row_id', $lockedOriginal->row_id)->exists()) {
                    throw new DomainException('The journal has already been reversed.');
                }

                $reversal = JournalEntry::query()->create([
                    'transaction_date' => $reversalDate,
                    'sequence_number' => $lockedOriginal->sequence_number,
                    'source_type' => 'journal_reversal',
                    'source_row_id' => $lockedOriginal->row_id,
                    'description' => $reason ?: "Reversal of journal {$lockedOriginal->id}",
                    'status' => 'draft',
                    'reversed_entry_row_id' => $lockedOriginal->row_id,
                    'created_by_user_id' => $platformUserId,
                ]);

                foreach ($lockedOriginal->lines as $line) {
                    JournalLine::query()->create([
                        'journal_entry_row_id' => $reversal->row_id,
                        'line_number' => $line->line_number,
                        'account_row_id' => $line->account_row_id,
                        'organization_unit_row_id' => $line->organization_unit_row_id,
                        'description' => $line->description,
                        'debit' => $line->credit,
                        'credit' => $line->debit,
                    ]);
                }

                return $reversal;
            },
            5,
        );

        return $this->postingService->post($reversal, $platformUserId);
    }

    /**
     * @param  array<int>  $entryRowIds
     * @return array{reversed: list<JournalEntry>, errors: list<string>}
     */
    public function bulkReverse(
        array $entryRowIds,
        \DateTimeInterface|string $reversalDate,
        int $platformUserId,
        ?string $reason = null,
    ): array {
        $reversed = [];
        $errors = [];

        $entries = JournalEntry::query()
            ->whereIn('row_id', $entryRowIds)
            ->get();

        foreach ($entries as $entry) {
            try {
                $reversed[] = $this->reverse(
                    original: $entry,
                    reversalDate: $reversalDate,
                    platformUserId: $platformUserId,
                    reason: $reason ?: "Reversal of journal {$entry->id}",
                );
            } catch (DomainException $e) {
                $errors[] = "Jurnal #{$entry->id}: {$e->getMessage()}";
            } catch (\Throwable $e) {
                $errors[] = "Jurnal #{$entry->id}: gagal dibatalkan ({$e->getMessage()})";
            }
        }

        return [
            'reversed' => $reversed,
            'errors' => $errors,
        ];
    }
}
