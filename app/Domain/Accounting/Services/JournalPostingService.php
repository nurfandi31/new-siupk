<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Tenancy\Services\TenantSequenceService;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final class JournalPostingService
{
    public function __construct(
        private readonly TenantSequenceService $sequenceService,
    ) {}

    public function post(JournalEntry $entry, int $platformUserId): JournalEntry
    {
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');

        return DB::connection($connectionName)->transaction(
            function (ConnectionInterface $connection) use ($entry, $platformUserId): JournalEntry {
                /** @var JournalEntry $lockedEntry */
                $lockedEntry = JournalEntry::query()
                    ->whereKey($entry->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedEntry->status !== 'draft') {
                    throw new DomainException("Only draft journals may be posted; current status is [{$lockedEntry->status}].");
                }

                $period = FiscalPeriod::query()
                    ->whereDate('starts_at', '<=', $lockedEntry->transaction_date)
                    ->whereDate('ends_at', '>=', $lockedEntry->transaction_date)
                    ->lockForUpdate()
                    ->first();

                if ($period === null) {
                    throw new DomainException('No fiscal period contains the journal transaction date.');
                }

                if ($period->status !== 'open') {
                    throw new DomainException('The fiscal period is not open.');
                }

                $totals = $connection->table('journal_lines')
                    ->where('tenant_id', $lockedEntry->tenant_id)
                    ->where('journal_entry_row_id', $lockedEntry->row_id)
                    ->selectRaw('COUNT(*) AS line_count')
                    ->selectRaw('CAST(COALESCE(SUM(debit), 0) AS CHAR) AS debit_total')
                    ->selectRaw('CAST(COALESCE(SUM(credit), 0) AS CHAR) AS credit_total')
                    ->first();

                if ($totals === null || (int) $totals->line_count < 2) {
                    throw new DomainException('A journal entry must contain at least two lines.');
                }

                $debit = (string) $totals->debit_total;
                $credit = (string) $totals->credit_total;

                if (bccomp($debit, $credit, 2) !== 0) {
                    throw new DomainException("Journal is not balanced: debit [{$debit}] versus credit [{$credit}].");
                }

                if (bccomp($debit, '0.00', 2) <= 0) {
                    throw new DomainException('A journal entry must have a value greater than zero.');
                }

                if ($lockedEntry->journal_number === null || $lockedEntry->journal_number === '') {
                    $lockedEntry->journal_number = $this->generateJournalNumber(
                        $lockedEntry->transaction_date,
                    );
                }

                $lockedEntry->status = 'posted';
                $lockedEntry->posted_at = now();
                $lockedEntry->posted_by_user_id = $platformUserId;
                $lockedEntry->save();

                return $lockedEntry->fresh(['lines.account']) ?? $lockedEntry;
            },
            5,
        );
    }

    /**
     * Format: YYMMNNN (contoh: 2608001).
     *
     * Menggunakan sequence per bulan sehingga nomor reset setiap bulan baru.
     * Maksimal 999 jurnal per bulan; jika lebih, angka tetap bertambah (4+ digit).
     */
    private function generateJournalNumber(\DateTimeInterface $transactionDate): string
    {
        $prefix = $transactionDate->format('ym');

        $sequenceName = 'journal_number:'.$prefix;

        $seq = $this->sequenceService->next($sequenceName);

        $number = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        return $prefix.$number;
    }
}
