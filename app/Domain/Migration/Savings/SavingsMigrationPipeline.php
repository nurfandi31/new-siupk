<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings;

use App\Domain\Migration\Savings\DTO\NormalizedSavingsAccount;
use App\Domain\Migration\Savings\DTO\NormalizedSavingsProduct;
use App\Domain\Migration\Savings\DTO\NormalizedSavingsTransaction;
use App\Domain\Migration\Support\LegacyConnection;
use App\Domain\Savings\Services\SavingsModuleManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class SavingsMigrationPipeline
{
    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacySavingsExtractor $extractor,
        private LegacySavingsNormalizer $normalizer,
        private LegacySavingsLoader $loader,
        private SavingsMigrationReconciler $reconciler,
        private SavingsModuleManager $modules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $suffix,
        bool $dryRun,
        int $chunk,
        bool $failFast,
        bool $skipAccounts,
        bool $skipTransactions,
        bool $skipReconcile,
    ): array {
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix must be numeric.');
        }

        $accountsTable = $this->legacy->simpananAnggotaTable($suffix);
        $realTable = $this->legacy->realSimpananTable($suffix);

        if (! $skipAccounts && ! $this->legacy->tableExists($accountsTable)) {
            throw new RuntimeException("Legacy table [{$accountsTable}] not found.");
        }
        if (! $skipTransactions && ! $this->legacy->tableExists($realTable)) {
            throw new RuntimeException("Legacy table [{$realTable}] not found.");
        }

        $isMapped = function (string $sourceTable, string $sourceId, string $secondary = ''): bool {
            return DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
                ->table('legacy_record_mappings')
                ->where('tenant_id', $this->context->id())
                ->where('source_table', $sourceTable)
                ->where('source_id', $sourceId)
                ->where('source_secondary_key', $secondary)
                ->exists();
        };

        $productRows = $this->buildProducts($suffix, $dryRun);
        $counts = [
            'source_products' => count($productRows),
            'source_accounts' => $skipAccounts ? 0 : $this->extractor->simpananAnggotaCount($suffix),
            'source_transactions' => $skipTransactions ? 0 : $this->extractor->realSimpananCount($suffix),
            'would_insert_accounts' => 0,
            'would_skip_accounts' => 0,
            'would_insert_transactions' => 0,
            'would_skip_transactions' => 0,
            'inserted_accounts' => 0,
            'inserted_transactions' => 0,
            'opening_transactions' => 0,
            'recalculated_accounts' => 0,
        ];
        $errors = [];
        $warnings = [];
        $accounts = [];

        if (! $skipAccounts) {
            $this->normalizer->warmProducts();
            foreach ($this->extractor->simpananAnggotaChunks($suffix, $chunk) as $rows) {
                $norm = [];
                foreach ($rows as $row) {
                    $result = $this->normalizer->normalizeAccount($row, $accountsTable, $isMapped);
                    if ($result['skip']) {
                        $counts['would_skip_accounts']++;

                        continue;
                    }
                    if ($result['error'] !== null) {
                        $errors[] = $result['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    if ($result['ok'] instanceof NormalizedSavingsAccount) {
                        $norm[] = $result['ok'];
                        $counts['would_insert_accounts']++;
                    }
                }
                array_push($accounts, ...$norm);
            }
        }

        if (! $skipTransactions && ! ($failFast && $errors !== [])) {
            foreach ($this->extractor->realSimpananChunks($suffix, $chunk) as $rows) {
                foreach ($rows as $row) {
                    $result = $this->normalizer->normalizeTransaction($row, $realTable, $isMapped);
                    if ($result['skip']) {
                        $counts['would_skip_transactions']++;

                        continue;
                    }
                    if ($result['error'] !== null) {
                        $errors[] = $result['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    if ($result['ok'] instanceof NormalizedSavingsTransaction) {
                        $counts['would_insert_transactions']++;
                    }
                }
            }
        }

        $summary = [
            'dry_run' => $dryRun,
            'batch_row_id' => null,
            ...$counts,
            'errors' => array_slice($errors, 0, 50),
            'error_count' => count($errors),
            'warnings' => [],
            'warning_count' => 0,
            'recon' => [],
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
            if (! $skipAccounts) {
                $productMap = $this->loader->loadProducts($productRows);
                $this->normalizer->warmProducts();

                // Reload accounts after products exist so product_row_id is final.
                $accounts = [];
                foreach ($this->extractor->simpananAnggotaChunks($suffix, $chunk) as $rows) {
                    $norm = [];
                    foreach ($rows as $row) {
                        $result = $this->normalizer->normalizeAccount($row, $accountsTable, $isMapped);
                        if ($result['ok'] instanceof NormalizedSavingsAccount) {
                            $norm[] = $result['ok'];
                        }
                    }
                    if ($norm !== []) {
                        $load = $this->loader->loadAccounts($batchRowId, $accountsTable, $norm);
                        $summary['inserted_accounts'] += $load['inserted'];
                        foreach ($load['warnings'] as $warning) {
                            $warnings[] = $warning;
                        }
                    }
                    array_push($accounts, ...$norm);
                }

                unset($productMap);
            }

            if (! $skipTransactions && $this->legacy->tableExists($realTable)) {
                foreach ($this->extractor->realSimpananChunks($suffix, $chunk) as $rows) {
                    $norm = [];
                    foreach ($rows as $row) {
                        $result = $this->normalizer->normalizeTransaction($row, $realTable, $isMapped);
                        if ($result['ok'] instanceof NormalizedSavingsTransaction) {
                            $norm[] = $result['ok'];
                        }
                    }
                    if ($norm !== []) {
                        $load = $this->loader->loadTransactions(
                            $batchRowId,
                            $realTable,
                            $accountsTable,
                            $norm,
                        );
                        $summary['inserted_transactions'] += $load['inserted'];
                        foreach ($load['warnings'] as $warning) {
                            $warnings[] = $warning;
                        }
                    }
                }
            }

            if (! $skipTransactions && ! $skipAccounts && $accounts !== []) {
                $summary['opening_transactions'] = $this->loader->ensureOpeningTransactions(
                    $batchRowId,
                    $accountsTable,
                    $accounts,
                );
                $summary['recalculated_accounts'] = $this->loader->syncAccountBalances($accountsTable);
            }

            $summary['errors'] = array_slice($errors, 0, 50);
            $summary['error_count'] = count($errors);
            $summary['warnings'] = array_slice($warnings, 0, 50);
            $summary['warning_count'] = count($warnings);

            if (! $skipReconcile && ! $skipAccounts) {
                $summary['recon'] = $this->reconciler->run($batchRowId, $suffix);
                $ok = $this->reconciler->allCriticalMatched($summary['recon']) && $errors === [];
                $summary['status'] = $ok ? 'completed' : 'failed';
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

    /**
     * @return list<NormalizedSavingsProduct>
     */
    private function buildProducts(string $suffix, bool $dryRun): array
    {
        if (! $dryRun) {
            $this->modules->enable($this->context->id());
        }

        $byId = [];
        $usedIds = $this->extractor->usedJenisSimpananIds($suffix);
        $used = array_fill_keys($usedIds, true);
        foreach ($this->extractor->jenisSimpananRows() as $row) {
            $product = $this->normalizer->normalizeProduct($row);
            if ($product->legacyId > 0 && isset($used[$product->legacyId])) {
                $byId[$product->legacyId] = $product;
            }
        }

        foreach ($usedIds as $legacyId) {
            if (isset($byId[$legacyId])) {
                continue;
            }
            $byId[$legacyId] = new NormalizedSavingsProduct(
                legacyId: $legacyId,
                code: (string) $legacyId,
                name: 'Simpanan Legacy '.$legacyId,
                category: 'lainnya',
                formType: 1,
                minimumBalance: '0.00',
                sortOrder: $legacyId,
                snapshot: ['id' => $legacyId, 'synthetic' => true],
            );
        }

        return array_values($byId);
    }

    private function createBatch(string $suffix): int
    {
        $now = now()->format('Y-m-d H:i:s');

        return (int) DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
            ->table('legacy_migration_batches')->insertGetId([
                'tenant_id' => $this->context->id(),
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
        $now = now()->format('Y-m-d H:i:s');
        DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
            ->table('legacy_migration_batches')
            ->where('row_id', $batchRowId)
            ->update([
                'status' => $status,
                'completed_at' => $now,
                'summary' => json_encode($summary, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]);
    }
}
