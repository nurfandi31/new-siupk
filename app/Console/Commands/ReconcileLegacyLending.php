<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Lending\LendingMigrationReconciler;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Re-run §65 loan recon + exception inventory without reloading data.
 */
final class ReconcileLegacyLending extends Command
{
    protected $signature = 'legacy:reconcile-lending
        {tenant : Tenant row ID or code}
        {suffix : Legacy lokasi id}
        {--json : Machine-readable output}';

    protected $description = 'Reconcile legacy pinjaman/angsuran vs Next loans (counts, §65 balances, exceptions).';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        LendingMigrationReconciler $reconciler,
    ): int {
        $tenant = Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit((string) $this->argument('tenant')),
                fn ($q) => $q->whereKey((int) $this->argument('tenant')),
                fn ($q) => $q->where('code', (string) $this->argument('tenant')),
            )
            ->firstOrFail();

        $placement = $tenant->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $suffix = (string) $this->argument('suffix');
        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $conn = (string) config('tenancy.tenant_connection', 'tenant');
            $now = now()->format('Y-m-d H:i:s');
            $batchRowId = (int) DB::connection($conn)->table('legacy_migration_batches')->insertGetId([
                'tenant_id' => $context->id(),
                'public_id' => (string) Str::ulid(),
                'source_database' => (string) config('database.connections.legacy.database'),
                'source_suffix' => $suffix,
                'status' => 'reconciling',
                'started_at' => $now,
                'completed_at' => null,
                'source_checksum' => null,
                'target_checksum' => null,
                'summary' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'row_id');

            $results = $reconciler->run($batchRowId, $suffix);
            $ok = $reconciler->allCriticalMatched($results);

            DB::connection($conn)->table('legacy_migration_batches')->where('row_id', $batchRowId)->update([
                'status' => $ok ? 'reconciled' : 'recon_failed',
                'completed_at' => now()->format('Y-m-d H:i:s'),
                'summary' => json_encode(['recon' => $results], JSON_THROW_ON_ERROR),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            if ($this->option('json')) {
                $this->line(json_encode([
                    'batch_row_id' => $batchRowId,
                    'ok' => $ok,
                    'recon' => $results,
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return $ok ? self::SUCCESS : self::FAILURE;
            }

            $this->info("Recon batch={$batchRowId} tenant={$tenant->code} suffix={$suffix}");
            $this->table(
                ['scope', 'status', 'source', 'target', 'notes'],
                array_map(static function (array $r): array {
                    $details = $r['details'] ?? [];
                    $note = '';
                    if (isset($details['mismatched'])) {
                        $note = 'mismatched='.$details['mismatched'].' matched='.($details['matched'] ?? '');
                    } elseif (isset($details['count'])) {
                        $note = 'exceptions='.$details['count'];
                    } elseif (isset($details['skipped'])) {
                        $note = 'skipped='.$details['skipped'];
                    } elseif (isset($details['installment_rows'])) {
                        $note = 'orphan_rows='.$details['installment_rows'];
                    }

                    return [
                        $r['scope'] ?? '',
                        $r['status'] ?? '',
                        $r['source_count'] ?? '',
                        $r['target_count'] ?? '',
                        $note,
                    ];
                }, $results),
            );

            // Print loan_balance exception sample
            foreach ($results as $r) {
                if (($r['scope'] ?? '') !== 'loan_balance') {
                    continue;
                }
                $ex = $r['details']['exceptions'] ?? [];
                if ($ex === []) {
                    $this->info('loan_balance: all matched');
                    break;
                }
                $this->warn('loan_balance mismatches (sample up to 15):');
                foreach (array_slice($ex, 0, 15) as $item) {
                    $this->line('  loan_id='.($item['loan_id'] ?? '?').' type='.($item['type'] ?? ''));
                    if (isset($item['fields']) && is_array($item['fields'])) {
                        foreach ($item['fields'] as $field => $pair) {
                            if (is_array($pair)) {
                                $this->line('    '.$field.': legacy='.json_encode($pair['legacy'] ?? null)
                                    .' target='.json_encode($pair['target'] ?? null));
                            } else {
                                $this->line('    '.$field);
                            }
                        }
                    }
                }
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $context->clear();
            $connections->disconnect();
        }
    }
}
