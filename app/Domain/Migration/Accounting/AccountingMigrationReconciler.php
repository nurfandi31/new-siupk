<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Migration\Support\LegacyAmountParser;
use App\Domain\Migration\Support\LegacyConnection;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AccountingMigrationReconciler
{
    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacyAmountParser $amounts,
    ) {}

    /**
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, source_debit: ?string, target_debit: ?string, source_credit: ?string, target_credit: ?string, details: array<string, mixed>}>
     */
    public function run(
        int $batchRowId,
        string $suffix,
        ?string $fromDate,
        ?string $toDate,
    ): array {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $trxTable = $this->legacy->transaksiTable($suffix);
        $saldoTable = $this->legacy->saldoTable($suffix);
        $results = [];

        // Scope by source_table for tenant (all batches) so re-runs stay reconcilable.
        $sourceCount = $this->countLegacyTransaksi($trxTable, $fromDate, $toDate);
        $mappingCount = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $trxTable)
            ->where('target_table', 'journal_entries')
            ->count();
        $entryCount = (int) DB::connection($conn)->table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->where('source_type', 'legacy_transaksi')
            ->count();

        $results[] = $this->persist($batchRowId, 'transaksi_count', null, null, [
            'source_count' => $sourceCount,
            'target_count' => $mappingCount,
            'status' => $sourceCount === $mappingCount ? 'matched' : 'mismatch',
            'details' => [
                'legacy_migratable' => $sourceCount,
                'mappings' => $mappingCount,
                'journal_entries_legacy_source' => $entryCount,
                'batch_row_id' => $batchRowId,
            ],
        ]);

        // lines = 2x entries for source table mappings
        $lineCount = (int) DB::connection($conn)->table('journal_lines as l')
            ->join('journal_entries as e', function ($j): void {
                $j->on('e.tenant_id', '=', 'l.tenant_id')
                    ->on('e.row_id', '=', 'l.journal_entry_row_id');
            })
            ->join('legacy_record_mappings as m', function ($j) use ($trxTable): void {
                $j->on('m.tenant_id', '=', 'e.tenant_id')
                    ->on('m.target_row_id', '=', 'e.row_id')
                    ->where('m.source_table', $trxTable)
                    ->where('m.target_table', 'journal_entries');
            })
            ->where('l.tenant_id', $tenantId)
            ->count();

        $expectedLines = $mappingCount * 2;
        $results[] = $this->persist($batchRowId, 'journal_lines_count', null, null, [
            'source_count' => $expectedLines,
            'target_count' => $lineCount,
            'status' => $lineCount === $expectedLines ? 'matched' : 'mismatch',
            'details' => ['expected' => $expectedLines, 'actual' => $lineCount],
        ]);

        // sums — legacy abs(jumlah) for non-zero rows vs target lines
        $legacySum = $this->sumLegacyJumlah($trxTable, $fromDate, $toDate);
        $debitSum = (string) (DB::connection($conn)->table('journal_lines as l')
            ->join('legacy_record_mappings as m', function ($j) use ($trxTable): void {
                $j->on('m.tenant_id', '=', 'l.tenant_id')
                    ->on('m.target_row_id', '=', 'l.journal_entry_row_id')
                    ->where('m.source_table', $trxTable)
                    ->where('m.target_table', 'journal_entries');
            })
            ->where('l.tenant_id', $tenantId)
            ->sum('l.debit') ?? '0');
        $creditSum = (string) (DB::connection($conn)->table('journal_lines as l')
            ->join('legacy_record_mappings as m', function ($j) use ($trxTable): void {
                $j->on('m.tenant_id', '=', 'l.tenant_id')
                    ->on('m.target_row_id', '=', 'l.journal_entry_row_id')
                    ->where('m.source_table', $trxTable)
                    ->where('m.target_table', 'journal_entries');
            })
            ->where('l.tenant_id', $tenantId)
            ->sum('l.credit') ?? '0');

        $debitSum = bcadd($debitSum, '0', 2);
        $creditSum = bcadd($creditSum, '0', 2);
        $sumsOk = bccomp($legacySum, $debitSum, 2) === 0 && bccomp($debitSum, $creditSum, 2) === 0;

        $results[] = $this->persist($batchRowId, 'transaksi_sums', null, null, [
            'source_count' => $sourceCount,
            'target_count' => $mappingCount,
            'source_debit' => $legacySum,
            'target_debit' => $debitSum,
            'source_credit' => $legacySum,
            'target_credit' => $creditSum,
            'status' => $sumsOk ? 'matched' : 'mismatch',
            'details' => [],
        ]);

        // unbalanced entries
        $unbalanced = (int) DB::connection($conn)->selectOne(
            "SELECT COUNT(*) AS c FROM (
                SELECT e.row_id
                FROM journal_entries e
                INNER JOIN legacy_record_mappings m
                  ON m.tenant_id = e.tenant_id AND m.target_row_id = e.row_id
                 AND m.source_table = ? AND m.target_table = 'journal_entries'
                INNER JOIN journal_lines l ON l.tenant_id = e.tenant_id AND l.journal_entry_row_id = e.row_id
                WHERE e.tenant_id = ?
                GROUP BY e.row_id
                HAVING ROUND(SUM(l.debit), 2) <> ROUND(SUM(l.credit), 2)
            ) x",
            [$trxTable, $tenantId],
        )->c;

        $results[] = $this->persist($batchRowId, 'journals_balanced', null, null, [
            'source_count' => 0,
            'target_count' => $unbalanced,
            'status' => $unbalanced === 0 ? 'matched' : 'mismatch',
            'details' => ['unbalanced_entries' => $unbalanced],
        ]);

        // openings sample recon
        if ($this->legacy->tableExists($saldoTable)) {
            $openMismatch = 0;
            $openChecked = 0;
            $legacyOpenings = $this->legacy->select(
                "SELECT kode_akun, tahun, debit, kredit FROM `{$saldoTable}` WHERE CAST(bulan AS UNSIGNED) = 0",
            );
            foreach ($legacyOpenings as $row) {
                $code = trim((string) $row->kode_akun);
                $year = (int) $row->tahun;
                // Non-COA saldo keys (desa/kec alokasi) — not migration targets.
                if (preg_match('/^[1-5](\.\d+){1,3}$/', $code) !== 1) {
                    continue;
                }
                try {
                    $d = $this->parseOpeningSide($row->debit ?? '0');
                    $c = $this->parseOpeningSide($row->kredit ?? $row->credit ?? '0');
                } catch (InvalidArgumentException) {
                    $openMismatch++;
                    $openChecked++;

                    continue;
                }
                if (bccomp($d, '0.00', 2) === 0 && bccomp($c, '0.00', 2) === 0) {
                    continue;
                }
                $openChecked++;
                $account = DB::connection($conn)->table('accounts')
                    ->where('tenant_id', $tenantId)
                    ->where('code', $code)
                    ->first(['row_id']);
                if ($account === null) {
                    $openMismatch++;

                    continue;
                }
                $target = DB::connection($conn)->table('account_opening_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('account_row_id', $account->row_id)
                    ->where('fiscal_year', $year)
                    ->first(['debit', 'credit']);
                if ($target === null
                    || bccomp(bcadd((string) $target->debit, '0', 2), $d, 2) !== 0
                    || bccomp(bcadd((string) $target->credit, '0', 2), $c, 2) !== 0
                ) {
                    $openMismatch++;
                }
            }
            $results[] = $this->persist($batchRowId, 'openings', null, null, [
                'source_count' => $openChecked,
                'target_count' => $openChecked - $openMismatch,
                'status' => $openMismatch === 0 ? 'matched' : 'mismatch',
                'details' => ['checked' => $openChecked, 'mismatches' => $openMismatch],
            ]);
        }

        return $results;
    }

    public function allCriticalMatched(array $results): bool
    {
        foreach ($results as $r) {
            if (($r['status'] ?? '') !== 'matched') {
                return false;
            }
        }

        return true;
    }

    private function countLegacyTransaksi(string $table, ?string $fromDate, ?string $toDate): int
    {
        $this->legacy->assertSafeTableName($table);
        // Match normalizer: skip empty/zero jumlah (placeholders), count migratable rows only.
        $sql = "SELECT COUNT(*) AS c FROM `{$table}`
                WHERE deleted_at IS NULL
                  AND jumlah IS NOT NULL
                  AND TRIM(CAST(jumlah AS CHAR)) NOT IN ('', '0', '0.0', '0.00', '0,00')";
        $bindings = [];
        if ($fromDate) {
            $sql .= ' AND tgl_transaksi >= ?';
            $bindings[] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND tgl_transaksi <= ?';
            $bindings[] = $toDate;
        }

        return (int) ($this->legacy->selectOne($sql, $bindings)->c ?? 0);
    }

    private function sumLegacyJumlah(string $table, ?string $fromDate, ?string $toDate): string
    {
        $this->legacy->assertSafeTableName($table);
        $sql = "SELECT jumlah FROM `{$table}`
                WHERE deleted_at IS NULL
                  AND jumlah IS NOT NULL
                  AND TRIM(CAST(jumlah AS CHAR)) NOT IN ('', '0', '0.0', '0.00', '0,00')";
        $bindings = [];
        if ($fromDate) {
            $sql .= ' AND tgl_transaksi >= ?';
            $bindings[] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND tgl_transaksi <= ?';
            $bindings[] = $toDate;
        }
        $sum = '0.00';
        foreach ($this->legacy->select($sql, $bindings) as $row) {
            try {
                $parsed = $this->amounts->parse($row->jumlah ?? '');
                if (bccomp($parsed, '0.00', 2) <= 0) {
                    continue;
                }
                $sum = bcadd($sum, $parsed, 2);
            } catch (InvalidArgumentException) {
                // already failed at load; treat as recon mismatch by not adding
            }
        }

        return $sum;
    }

    private function parseOpeningSide(mixed $raw): string
    {
        if ($raw === null || trim((string) $raw) === '' || (string) $raw === '0') {
            return '0.00';
        }
        $s = trim((string) $raw);
        if ($s === '0.0' || $s === '0.00' || $s === '0,00') {
            return '0.00';
        }

        return $this->amounts->parse($raw);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persist(int $batchRowId, string $scope, ?string $periodStart, ?string $periodEnd, array $data): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');

        $payload = [
            'scope' => $scope,
            'status' => (string) $data['status'],
            'source_count' => (int) ($data['source_count'] ?? 0),
            'target_count' => (int) ($data['target_count'] ?? 0),
            'source_debit' => $data['source_debit'] ?? null,
            'target_debit' => $data['target_debit'] ?? null,
            'source_credit' => $data['source_credit'] ?? null,
            'target_credit' => $data['target_credit'] ?? null,
            'details' => $data['details'] ?? [],
        ];

        DB::connection($conn)->table('migration_reconciliation_results')->insert([
            'tenant_id' => $tenantId,
            'batch_row_id' => $batchRowId,
            'scope' => $scope,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'source_count' => $payload['source_count'],
            'target_count' => $payload['target_count'],
            'source_debit' => $payload['source_debit'],
            'target_debit' => $payload['target_debit'],
            'source_credit' => $payload['source_credit'],
            'target_credit' => $payload['target_credit'],
            'source_balance' => null,
            'target_balance' => null,
            'status' => $payload['status'],
            'difference_details' => json_encode($payload['details'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $payload;
    }
}
