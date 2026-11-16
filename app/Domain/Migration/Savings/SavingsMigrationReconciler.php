<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings;

use App\Domain\Migration\Support\LegacyAmountParser;
use App\Domain\Migration\Support\LegacyConnection;
use App\Domain\Savings\SavingsModule;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SavingsMigrationReconciler
{
    private const MONEY_TOLERANCE = '0.02';

    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacySavingsExtractor $extractor,
        private LegacyAmountParser $amounts,
    ) {}

    /**
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}>
     */
    public function run(int $batchRowId, string $suffix): array
    {
        return array_merge(
            $this->countScopes($batchRowId, $suffix),
            $this->balanceScope($batchRowId, $suffix),
        );
    }

    /**
     * @param  list<array{scope: string, status: string}>  $results
     */
    public function allCriticalMatched(array $results): bool
    {
        foreach ($results as $result) {
            if (($result['scope'] ?? '') === 'savings_accounts' && ($result['status'] ?? '') !== 'matched') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}>
     */
    private function countScopes(int $batchRowId, string $suffix): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $accountsTable = $this->legacy->simpananAnggotaTable($suffix);
        $realTable = $this->legacy->realSimpananTable($suffix);

        $srcAccounts = $this->extractor->simpananAnggotaCount($suffix);
        $mappedAccounts = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $accountsTable)
            ->where('source_secondary_key', 'savings_account')
            ->count();

        $srcTransactions = $this->extractor->realSimpananCount($suffix);
        $mappedTransactions = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $realTable)
            ->where('source_secondary_key', 'savings_transaction')
            ->count();

        return [
            $this->persist($batchRowId, 'savings_accounts', [
                'source_count' => $srcAccounts,
                'target_count' => $mappedAccounts,
                'status' => $this->partialStatus($srcAccounts, $mappedAccounts),
                'details' => ['skipped' => max(0, $srcAccounts - $mappedAccounts)],
            ]),
            $this->persist($batchRowId, 'savings_transactions', [
                'source_count' => $srcTransactions,
                'target_count' => $mappedTransactions,
                'status' => $this->partialStatus($srcTransactions, $mappedTransactions),
                'details' => ['skipped' => max(0, $srcTransactions - $mappedTransactions)],
            ]),
        ];
    }

    /**
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}>
     */
    private function balanceScope(int $batchRowId, string $suffix): array
    {
        $sourceTable = $this->legacy->simpananAnggotaTable($suffix);
        if (! $this->legacy->tableExists($sourceTable)) {
            return [];
        }

        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $realTable = $this->legacy->realSimpananTable($suffix);
        $accountsTable = SavingsModule::table('accounts', $tenantId);

        $lastSum = 'NULL';
        if ($this->legacy->tableExists($realTable)) {
            // Ignore zero placeholder rows (real_d/real_k = 0). For accounts
            // without real mutations the opening/current amount is `jumlah`,
            // which the loader materializes as a synthetic saldo_awal.
            $lastSum = "(SELECT r.sum FROM `{$realTable}` r
                WHERE r.cif = s.id
                  AND (COALESCE(r.real_k, 0) <> 0 OR COALESCE(r.real_d, 0) <> 0)
                ORDER BY r.tgl_transaksi DESC, r.id DESC LIMIT 1)";
        }

        $sourceRows = $this->legacy->select(
            "SELECT s.id, s.jumlah, {$lastSum} AS last_sum FROM `{$sourceTable}` s ORDER BY s.id"
        );

        $mapped = DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $sourceTable)
            ->where('source_secondary_key', 'savings_account')
            ->pluck('target_row_id', 'source_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $targetBalances = [];
        foreach (array_chunk(array_values($mapped), 500) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            foreach (DB::connection($conn)->table($accountsTable)
                ->where('tenant_id', $tenantId)
                ->whereIn('row_id', $chunk)
                ->get(['row_id', 'balance']) as $row) {
                $targetBalances[(int) $row->row_id] = (string) $row->balance;
            }
        }

        $checked = 0;
        $matched = 0;
        $mismatches = [];
        foreach ($sourceRows as $row) {
            $checked++;
            $legacyId = (int) $row->id;
            $expected = $this->money($row->last_sum ?? $row->jumlah ?? '0');
            $targetRowId = $mapped[(string) $legacyId] ?? null;
            $actual = $targetRowId !== null ? ($targetBalances[$targetRowId] ?? null) : null;

            if ($actual === null) {
                $mismatches[] = [
                    'account_id' => $legacyId,
                    'type' => 'missing_on_target',
                    'expected' => $expected,
                ];

                continue;
            }

            if (! $this->moneyEq($expected, $this->money($actual))) {
                $mismatches[] = [
                    'account_id' => $legacyId,
                    'type' => 'balance_mismatch',
                    'expected' => $expected,
                    'actual' => $this->money($actual),
                ];
            } else {
                $matched++;
            }
        }

        $status = $mismatches === []
            ? 'matched'
            : ($matched > 0 ? 'partial' : 'mismatch');

        return [
            $this->persist($batchRowId, 'savings_balance', [
                'source_count' => $checked,
                'target_count' => $matched,
                'status' => $status,
                'details' => [
                    'checked' => $checked,
                    'matched' => $matched,
                    'mismatched' => count($mismatches),
                    'tolerance' => self::MONEY_TOLERANCE,
                    'exceptions' => array_slice($mismatches, 0, 50),
                    'exceptions_truncated' => count($mismatches) > 50,
                ],
            ]),
        ];
    }

    private function partialStatus(int $source, int $target): string
    {
        if ($source === $target) {
            return 'matched';
        }
        if ($target > 0 && $target < $source) {
            return 'partial';
        }
        if ($source === 0 && $target === 0) {
            return 'matched';
        }

        return 'mismatch';
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (is_int($value) || is_float($value)) {
            return number_format(round((float) $value, 2), 2, '.', '');
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return '0.00';
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $raw) === 1) {
            return number_format(round((float) $raw, 2), 2, '.', '');
        }
        try {
            $parsed = $this->amounts->parseSigned($raw);
            $amount = $parsed['amount'];

            return $parsed['negative'] ? bcsub('0.00', $amount, 2) : $amount;
        } catch (InvalidArgumentException) {
            return '0.00';
        }
    }

    private function moneyEq(string $left, string $right): bool
    {
        return abs((float) bcsub($left, $right, 2)) <= (float) self::MONEY_TOLERANCE;
    }

    /**
     * @param  array{source_count: int, target_count: int, status: string, details: array<string, mixed>}  $data
     * @return array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}
     */
    private function persist(int $batchRowId, string $scope, array $data): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');

        DB::connection($conn)->table('migration_reconciliation_results')->insert([
            'tenant_id' => $tenantId,
            'batch_row_id' => $batchRowId,
            'scope' => $scope,
            'period_start' => null,
            'period_end' => null,
            'source_count' => $data['source_count'],
            'target_count' => $data['target_count'],
            'source_debit' => null,
            'target_debit' => null,
            'source_credit' => null,
            'target_credit' => null,
            'source_balance' => null,
            'target_balance' => null,
            'status' => $data['status'],
            'difference_details' => json_encode($data['details'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'scope' => $scope,
            'status' => $data['status'],
            'source_count' => $data['source_count'],
            'target_count' => $data['target_count'],
            'details' => $data['details'],
        ];
    }
}
