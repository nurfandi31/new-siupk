<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Services\MonthlyBalanceRecalculator;
use App\Domain\Migration\Accounting\DTO\NormalizedJournal;
use App\Domain\Migration\Accounting\DTO\NormalizedOpening;
use App\Domain\Migration\Support\LegacyConnection;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class AccountingMigrationPipeline
{
    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacyAccountingExtractor $extractor,
        private LegacyAccountingNormalizer $normalizer,
        private LegacyJournalLoader $journalLoader,
        private LegacyOpeningBalanceLoader $openingLoader,
        private AccountingMigrationReconciler $reconciler,
        private MonthlyBalanceRecalculator $recalculator,
        private TenantSequenceService $sequences,
    ) {}

    /**
     * @return array{
     *   dry_run: bool,
     *   batch_row_id: int|null,
     *   source_count: int,
     *   would_insert_journals: int,
     *   would_skip_journals: int,
     *   would_insert_openings: int,
     *   inserted_journals: int,
     *   inserted_openings: int,
     *   errors: list<string>,
     *   recon: list<array<string, mixed>>,
     *   years: list<int>,
     *   status: string
     * }
     */
    public function run(
        string $suffix,
        bool $dryRun,
        int $chunk,
        ?string $fromDate,
        ?string $toDate,
        bool $failFast,
        bool $skipOpenings,
        bool $skipJournals,
        bool $skipRecalc,
        bool $skipReconcile,
    ): array {
        $suffix = (string) $suffix;
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix must be numeric.');
        }

        $trxTable = $this->legacy->transaksiTable($suffix);
        $saldoTable = $this->legacy->saldoTable($suffix);

        if (! $this->legacy->tableExists($trxTable)) {
            throw new RuntimeException("Legacy table [{$trxTable}] not found.");
        }

        $sourceCount = $this->extractor->activeTransaksiCount($suffix, $fromDate, $toDate);
        $range = $this->extractor->dateRange($suffix, $fromDate, $toDate);

        $this->assertFiscalCoverage($range['min'], $range['max']);
        $this->normalizer->warmCaches();

        $errors = [];
        $wouldJournals = 0;
        $wouldSkip = 0;
        $wouldOpenings = 0;
        $normalizedOpenings = [];
        $years = [];

        $isMapped = function (string $sourceTable, string $sourceId, string $secondary = ''): bool {
            $tenantId = $this->context->id();
            $conn = (string) config('tenancy.tenant_connection', 'tenant');

            return DB::connection($conn)->table('legacy_record_mappings')
                ->where('tenant_id', $tenantId)
                ->where('source_table', $sourceTable)
                ->where('source_id', $sourceId)
                ->where('source_secondary_key', $secondary)
                ->exists();
        };

        if (! $skipOpenings && $this->legacy->tableExists($saldoTable)) {
            foreach ($this->extractor->openings($suffix) as $row) {
                $r = $this->normalizer->normalizeOpening($row, $saldoTable, $isMapped);
                if ($r['skip']) {
                    continue;
                }
                if ($r['error'] !== null) {
                    $errors[] = $r['error'];
                    if ($failFast && ! $dryRun) {
                        break;
                    }

                    continue;
                }
                /** @var NormalizedOpening $ok */
                $ok = $r['ok'];
                $normalizedOpenings[] = $ok;
                $wouldOpenings++;
                $years[$ok->fiscalYear] = true;
            }
        }

        // Dry-run / pre-validate journals without holding all rows.
        if (! $skipJournals) {
            foreach ($this->extractor->transaksiChunks($suffix, $chunk, $fromDate, $toDate) as $rows) {
                foreach ($rows as $row) {
                    $r = $this->normalizer->normalizeTransaksi($row, $trxTable, $isMapped);
                    if ($r['skip']) {
                        $wouldSkip++;

                        continue;
                    }
                    if ($r['error'] !== null) {
                        $errors[] = $r['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    /** @var NormalizedJournal $ok */
                    $ok = $r['ok'];
                    $wouldJournals++;
                    $years[(int) substr($ok->transactionDate, 0, 4)] = true;
                }
            }
        }

        $summary = [
            'dry_run' => $dryRun,
            'batch_row_id' => null,
            'source_count' => $sourceCount,
            'would_insert_journals' => $wouldJournals,
            'would_skip_journals' => $wouldSkip,
            'would_insert_openings' => $wouldOpenings,
            'inserted_journals' => 0,
            'inserted_openings' => 0,
            'errors' => array_slice($errors, 0, 50),
            'error_count' => count($errors),
            'recon' => [],
            'years' => array_map('intval', array_keys($years)),
            'status' => 'pending',
        ];

        if ($dryRun) {
            $summary['status'] = $errors === [] ? 'dry_run_ok' : 'dry_run_failed';

            return $summary;
        }

        if ($failFast && $errors !== []) {
            $summary['status'] = 'failed';

            return $summary;
        }

        $batchRowId = $this->createBatch($suffix);
        $summary['batch_row_id'] = $batchRowId;

        try {
            if (! $skipOpenings && $normalizedOpenings !== []) {
                $summary['inserted_openings'] = $this->openingLoader->load($batchRowId, $saldoTable, $normalizedOpenings);
            }

            // Second pass load: re-stream legacy rows (memory-safe). Mapped skips handle dry pre-pass noise.
            if (! $skipJournals) {
                foreach ($this->extractor->transaksiChunks($suffix, $chunk, $fromDate, $toDate) as $rows) {
                    $chunkNormalized = [];
                    foreach ($rows as $row) {
                        $r = $this->normalizer->normalizeTransaksi($row, $trxTable, $isMapped);
                        if ($r['skip'] || $r['error'] !== null || $r['ok'] === null) {
                            continue;
                        }
                        $chunkNormalized[] = $r['ok'];
                    }
                    if ($chunkNormalized !== []) {
                        $result = $this->journalLoader->loadChunk($batchRowId, $trxTable, $chunkNormalized);
                        $summary['inserted_journals'] += $result['inserted'];
                    }
                }
            }

            $this->bumpSequences();

            if (! $skipRecalc) {
                foreach (array_keys($years) as $year) {
                    for ($m = 1; $m <= 12; $m++) {
                        $this->recalculator->recalculate((int) $year, $m);
                    }
                }
            }

            if (! $skipReconcile) {
                $summary['recon'] = $this->reconciler->run($batchRowId, $suffix, $fromDate, $toDate);
                $ok = $this->reconciler->allCriticalMatched($summary['recon']);
                $summary['status'] = $ok && $errors === [] ? 'completed' : 'failed';
            } else {
                $summary['status'] = $errors === [] ? 'completed' : 'failed';
            }

            $this->finishBatch($batchRowId, $summary['status'], $summary);

            return $summary;
        } catch (\Throwable $e) {
            $this->finishBatch($batchRowId, 'failed', ['error' => $e->getMessage()] + $summary);
            throw $e;
        }
    }

    private function assertFiscalCoverage(?string $min, ?string $max): void
    {
        if ($min === null || $max === null) {
            return;
        }

        $missing = [];
        $cursor = new \DateTimeImmutable(substr($min, 0, 7).'-01');
        $end = new \DateTimeImmutable(substr($max, 0, 7).'-01');
        while ($cursor <= $end) {
            $y = (int) $cursor->format('Y');
            $m = (int) $cursor->format('n');
            $period = FiscalPeriod::query()
                ->where('fiscal_year', $y)
                ->where('fiscal_month', $m)
                ->first();

            if ($period === null) {
                $starts = CarbonImmutable::create($y, $m, 1)->startOfMonth();
                FiscalPeriod::query()->create([
                    'fiscal_year' => $y,
                    'fiscal_month' => $m,
                    'starts_at' => $starts->toDateString(),
                    'ends_at' => $starts->endOfMonth()->toDateString(),
                    'status' => 'open',
                ]);
            } elseif ($period->status !== 'open') {
                $missing[] = $cursor->format('Y-m').' ('.$period->status.')';
            }
            $cursor = $cursor->modify('+1 month');
        }

        if ($missing !== []) {
            $sample = implode(', ', array_slice($missing, 0, 12));
            throw new RuntimeException(
                'Non-open fiscal periods for: '.$sample.(count($missing) > 12 ? '…' : '')
            );
        }
    }

    private function createBatch(string $suffix): int
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');

        return (int) DB::connection($conn)->table('legacy_migration_batches')->insertGetId([
            'tenant_id' => $tenantId,
            'public_id' => (string) Str::ulid(),
            'source_database' => (string) config('database.connections.legacy.database'),
            'source_suffix' => $suffix,
            'status' => 'running',
            'started_at' => $now,
            'completed_at' => null,
            'source_checksum' => null,
            'target_checksum' => null,
            'summary' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'row_id');
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function finishBatch(int $batchRowId, string $status, array $summary): void
    {
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        DB::connection($conn)->table('legacy_migration_batches')
            ->where('row_id', $batchRowId)
            ->update([
                'status' => $status,
                'completed_at' => $now,
                'summary' => json_encode($summary, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]);
    }

    private function bumpSequences(): void
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        foreach (['journal_entries', 'journal_lines', 'account_opening_balances'] as $table) {
            $max = (int) DB::connection($conn)->table($table)
                ->where('tenant_id', $tenantId)
                ->max('id');
            $this->sequences->initializeAtLeast($table, $max + 1);
        }

        // Also invoke command path for full table set when available in context
        // (optional — above covers accounting).
    }
}
