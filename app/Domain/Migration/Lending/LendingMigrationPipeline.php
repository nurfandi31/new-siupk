<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending;

use App\Domain\Migration\Lending\DTO\NormalizedBeneficiary;
use App\Domain\Migration\Lending\DTO\NormalizedGroupLoan;
use App\Domain\Migration\Lending\DTO\NormalizedInstallment;
use App\Domain\Migration\Lending\DTO\NormalizedPayment;
use App\Domain\Migration\Support\LegacyConnection;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class LendingMigrationPipeline
{
    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacyLendingExtractor $extractor,
        private LegacyLendingNormalizer $normalizer,
        private LegacyLoanLoader $loader,
        private LendingMigrationReconciler $reconciler,
        private TenantSequenceService $sequences,
        private TenantLoanProductProvisioner $products,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $suffix,
        bool $dryRun,
        int $chunk,
        bool $failFast,
        bool $skipLoans,
        bool $skipBeneficiaries,
        bool $skipInstallments,
        bool $skipPayments,
        bool $skipReconcile,
    ): array {
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix must be numeric.');
        }

        $pkTable = $this->legacy->pinjamanKelompokTable($suffix);
        $paTable = $this->legacy->pinjamanAnggotaTable($suffix);
        $rencanaTable = $this->legacy->rencanaAngsuranTable($suffix);
        $realTable = $this->legacy->realAngsuranTable($suffix);

        if (! $skipLoans && ! $this->legacy->tableExists($pkTable)) {
            throw new RuntimeException("Legacy table [{$pkTable}] not found.");
        }

        $this->products->ensureDefaults();
        $this->normalizer->warmCaches();

        $isMapped = function (string $sourceTable, string $sourceId, string $secondary = ''): bool {
            return DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
                ->table('legacy_record_mappings')
                ->where('tenant_id', $this->context->id())
                ->where('source_table', $sourceTable)
                ->where('source_id', $sourceId)
                ->where('source_secondary_key', $secondary)
                ->exists();
        };

        $errors = [];
        $warnings = [];
        $counts = [
            'source_loans' => $skipLoans ? 0 : $this->extractor->pinjamanKelompokCount($suffix),
            'source_beneficiaries' => $skipBeneficiaries ? 0 : $this->extractor->pinjamanAnggotaCount($suffix),
            'source_installments' => $skipInstallments ? 0 : $this->extractor->rencanaCount($suffix),
            'source_payments' => $skipPayments ? 0 : $this->extractor->realCount($suffix),
            'would_insert_loans' => 0,
            'would_skip_loans' => 0,
            'would_insert_beneficiaries' => 0,
            'would_skip_beneficiaries' => 0,
            'would_insert_installments' => 0,
            'would_skip_installments' => 0,
            'would_insert_payments' => 0,
            'would_skip_payments' => 0,
            'inserted_loans' => 0,
            'inserted_beneficiaries' => 0,
            'inserted_installments' => 0,
            'inserted_payments' => 0,
        ];

        if (! $skipLoans) {
            foreach ($this->extractor->pinjamanKelompokChunks($suffix, $chunk) as $rows) {
                foreach ($rows as $row) {
                    $r = $this->normalizer->normalizeGroupLoan($row, $pkTable, $isMapped);
                    if ($r['skip']) {
                        $counts['would_skip_loans']++;

                        continue;
                    }
                    if ($r['error'] !== null) {
                        $errors[] = $r['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    $counts['would_insert_loans']++;
                }
            }
        }

        if (! $skipBeneficiaries && ! ($failFast && $errors !== []) && $this->legacy->tableExists($paTable)) {
            foreach ($this->extractor->pinjamanAnggotaChunks($suffix, $chunk) as $rows) {
                foreach ($rows as $row) {
                    $r = $this->normalizer->normalizeBeneficiary($row, $paTable, $isMapped);
                    if ($r['skip']) {
                        $counts['would_skip_beneficiaries']++;

                        continue;
                    }
                    if ($r['error'] !== null) {
                        $errors[] = $r['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    $counts['would_insert_beneficiaries']++;
                }
            }
        }

        if (! $skipInstallments && ! ($failFast && $errors !== []) && $this->legacy->tableExists($rencanaTable)) {
            foreach ($this->extractor->rencanaChunks($suffix, $chunk) as $rows) {
                foreach ($rows as $row) {
                    $r = $this->normalizer->normalizeInstallment($row, $rencanaTable, $isMapped);
                    if ($r['skip']) {
                        $counts['would_skip_installments']++;

                        continue;
                    }
                    if ($r['error'] !== null) {
                        $errors[] = $r['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    $counts['would_insert_installments']++;
                }
            }
        }

        if (! $skipPayments && ! ($failFast && $errors !== []) && $this->legacy->tableExists($realTable)) {
            foreach ($this->extractor->realChunks($suffix, $chunk) as $rows) {
                foreach ($rows as $row) {
                    $r = $this->normalizer->normalizePayment($row, $realTable, $isMapped);
                    if ($r['skip']) {
                        $counts['would_skip_payments']++;

                        continue;
                    }
                    if ($r['error'] !== null) {
                        $errors[] = $r['error'];
                        if ($failFast) {
                            break 2;
                        }

                        continue;
                    }
                    $counts['would_insert_payments']++;
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

        $this->normalizer->warmCaches();
        $this->reserveSequences($suffix, $skipLoans, $skipPayments);

        $batchRowId = $this->createBatch($suffix);
        $summary['batch_row_id'] = $batchRowId;

        try {
            if (! $skipLoans) {
                foreach ($this->extractor->pinjamanKelompokChunks($suffix, $chunk) as $rows) {
                    $norm = [];
                    foreach ($rows as $row) {
                        $r = $this->normalizer->normalizeGroupLoan($row, $pkTable, $isMapped);
                        if ($r['ok'] instanceof NormalizedGroupLoan) {
                            $norm[] = $r['ok'];
                        }
                    }
                    if ($norm !== []) {
                        $load = $this->loader->loadLoans($batchRowId, $pkTable, $norm);
                        $summary['inserted_loans'] += $load['inserted'];
                        foreach ($load['errors'] as $e) {
                            $errors[] = $e;
                        }
                    }
                }
            }

            if (! $skipBeneficiaries && $this->legacy->tableExists($paTable)) {
                foreach ($this->extractor->pinjamanAnggotaChunks($suffix, $chunk) as $rows) {
                    $norm = [];
                    foreach ($rows as $row) {
                        $r = $this->normalizer->normalizeBeneficiary($row, $paTable, $isMapped);
                        if ($r['ok'] instanceof NormalizedBeneficiary) {
                            $norm[] = $r['ok'];
                        }
                    }
                    if ($norm !== []) {
                        $load = $this->loader->loadBeneficiaries($batchRowId, $paTable, $norm);
                        $summary['inserted_beneficiaries'] += $load['inserted'];
                        foreach ($load['errors'] as $e) {
                            $errors[] = $e;
                        }
                        foreach ($load['warnings'] as $w) {
                            $warnings[] = $w;
                        }
                    }
                }
            }

            if (! $skipInstallments && $this->legacy->tableExists($rencanaTable)) {
                foreach ($this->extractor->rencanaChunks($suffix, $chunk) as $rows) {
                    $norm = [];
                    foreach ($rows as $row) {
                        $r = $this->normalizer->normalizeInstallment($row, $rencanaTable, $isMapped);
                        if ($r['ok'] instanceof NormalizedInstallment) {
                            $norm[] = $r['ok'];
                        }
                    }
                    if ($norm !== []) {
                        $load = $this->loader->loadInstallments($batchRowId, $rencanaTable, $norm);
                        $summary['inserted_installments'] += $load['inserted'];
                        foreach ($load['warnings'] as $w) {
                            $warnings[] = $w;
                        }
                    }
                }
            }

            if (! $skipPayments && $this->legacy->tableExists($realTable)) {
                foreach ($this->extractor->realChunks($suffix, $chunk) as $rows) {
                    $norm = [];
                    foreach ($rows as $row) {
                        $r = $this->normalizer->normalizePayment($row, $realTable, $isMapped);
                        if ($r['ok'] instanceof NormalizedPayment) {
                            $norm[] = $r['ok'];
                        }
                    }
                    if ($norm !== []) {
                        $load = $this->loader->loadPayments($batchRowId, $realTable, $norm);
                        $summary['inserted_payments'] += $load['inserted'];
                        foreach ($load['warnings'] as $w) {
                            $warnings[] = $w;
                        }
                    }
                }
            }

            // Always recompute installment paid progress from payment allocations (idempotent).
            if (! $skipPayments || ! $skipInstallments) {
                $summary['installments_progress_loans'] = $this->loader->applyPaymentProgressToInstallments();
            }

            $this->bumpSequences();

            $summary['errors'] = array_slice($errors, 0, 50);
            $summary['error_count'] = count($errors);
            $summary['warnings'] = array_slice($warnings, 0, 50);
            $summary['warning_count'] = count($warnings);

            if (! $skipReconcile) {
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

    private function reserveSequences(string $suffix, bool $skipLoans, bool $skipPayments): void
    {
        if (! $skipLoans && $this->legacy->tableExists($this->legacy->pinjamanKelompokTable($suffix))) {
            $max = (int) ($this->legacy->selectOne(
                'SELECT MAX(id) AS m FROM `'.$this->legacy->pinjamanKelompokTable($suffix).'`'
            )->m ?? 0);
            if ($max > 0) {
                $this->sequences->initializeAtLeast('loans:group_loan', $max + 1);
            }
        }
        if (! $skipPayments && $this->legacy->tableExists($this->legacy->realAngsuranTable($suffix))) {
            $max = (int) ($this->legacy->selectOne(
                'SELECT MAX(id) AS m FROM `'.$this->legacy->realAngsuranTable($suffix).'`'
            )->m ?? 0);
            if ($max > 0) {
                $this->sequences->initializeAtLeast('loan_payments', $max + 1);
            }
        }
    }

    private function bumpSequences(): void
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        $maxLoan = (int) DB::connection($conn)->table('loans')
            ->where('tenant_id', $tenantId)
            ->where('legacy_source', 'group_loan')
            ->max('id');
        $this->sequences->initializeAtLeast('loans:group_loan', $maxLoan + 1);

        foreach ([
            'loan_borrowers',
            'loan_beneficiaries',
            'loan_status_histories',
            'loan_installments',
            'loan_payments',
            'loan_payment_allocations',
        ] as $table) {
            $max = (int) DB::connection($conn)->table($table)->where('tenant_id', $tenantId)->max('id');
            $this->sequences->initializeAtLeast($table, $max + 1);
        }
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
