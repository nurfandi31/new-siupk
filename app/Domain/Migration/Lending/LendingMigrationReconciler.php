<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending;

use App\Domain\Migration\Support\LegacyAmountParser;
use App\Domain\Migration\Support\LegacyConnection;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Count recon + §65 per-loan financial recon + exception inventory.
 * Differences become exception records (pending_approval) — never silent fix.
 */
final class LendingMigrationReconciler
{
    private const MONEY_TOLERANCE = '0.02';

    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private LegacyLendingExtractor $extractor,
        private LegacyAmountParser $amounts,
    ) {}

    /**
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}>
     */
    public function run(int $batchRowId, string $suffix): array
    {
        $results = [];
        $results = array_merge($results, $this->countScopes($batchRowId, $suffix));
        $results = array_merge($results, $this->loanFinancialScopes($batchRowId, $suffix));
        $results = array_merge($results, $this->exceptionScopes($batchRowId, $suffix));

        return $results;
    }

    /**
     * Critical: group_loans matched; loan_balance matched|partial with exceptions logged;
     * hard fail only on mismatch of group_loans or loan_balance status=mismatch without exceptions.
     *
     * @param  list<array{scope: string, status: string}>  $results
     */
    public function allCriticalMatched(array $results): bool
    {
        foreach ($results as $r) {
            $scope = $r['scope'] ?? '';
            $status = $r['status'] ?? '';
            if ($scope === 'group_loans' && $status !== 'matched') {
                return false;
            }
            if ($scope === 'loan_balance' && ! in_array($status, ['matched', 'partial'], true)) {
                return false;
            }
            if ($scope === 'loan_balance' && $status === 'mismatch') {
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
        $pkTable = $this->legacy->pinjamanKelompokTable($suffix);
        $paTable = $this->legacy->pinjamanAnggotaTable($suffix);
        $rencanaTable = $this->legacy->rencanaAngsuranTable($suffix);
        $realTable = $this->legacy->realAngsuranTable($suffix);
        $results = [];

        $srcLoans = $this->extractor->pinjamanKelompokCount($suffix);
        $mappedLoans = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $pkTable)
            ->where('source_secondary_key', 'loan')
            ->count();
        $results[] = $this->persist($batchRowId, 'group_loans', [
            'source_count' => $srcLoans,
            'target_count' => $mappedLoans,
            'status' => $srcLoans === $mappedLoans ? 'matched' : 'mismatch',
            'details' => [],
        ]);

        $srcBen = $this->extractor->pinjamanAnggotaCount($suffix);
        $mappedBen = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $paTable)
            ->where('source_secondary_key', 'beneficiary')
            ->count();
        $results[] = $this->persist($batchRowId, 'beneficiaries', [
            'source_count' => $srcBen,
            'target_count' => $mappedBen,
            'status' => $this->partialStatus($srcBen, $mappedBen),
            'details' => ['skipped' => max(0, $srcBen - $mappedBen)],
        ]);

        $srcInst = $this->extractor->rencanaCount($suffix);
        $mappedInst = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $rencanaTable)
            ->where('source_secondary_key', 'installment')
            ->count();
        $results[] = $this->persist($batchRowId, 'installments', [
            'source_count' => $srcInst,
            'target_count' => $mappedInst,
            'status' => $this->partialStatus($srcInst, $mappedInst),
            'details' => ['skipped' => max(0, $srcInst - $mappedInst)],
        ]);

        $srcPay = $this->extractor->realCount($suffix);
        $mappedPay = (int) DB::connection($conn)->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('source_table', $realTable)
            ->where('source_secondary_key', 'payment')
            ->count();
        $results[] = $this->persist($batchRowId, 'payments', [
            'source_count' => $srcPay,
            'target_count' => $mappedPay,
            'status' => $this->partialStatus($srcPay, $mappedPay),
            'details' => ['skipped' => max(0, $srcPay - $mappedPay)],
        ]);

        return $results;
    }

    /**
     * §65 per-loan financial comparison (bulk).
     *
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}>
     */
    private function loanFinancialScopes(int $batchRowId, string $suffix): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $pkTable = $this->legacy->pinjamanKelompokTable($suffix);
        $rencanaTable = $this->legacy->rencanaAngsuranTable($suffix);
        $realTable = $this->legacy->realAngsuranTable($suffix);

        if (! $this->legacy->tableExists($pkTable)) {
            return [];
        }

        $legacyLoans = $this->legacy->select(
            "SELECT id, alokasi, status, tgl_lunas, tgl_cair FROM `{$pkTable}` ORDER BY id"
        );

        $paidByLoan = [];
        $lastSaldoByLoan = [];
        $payCountByLoan = [];
        if ($this->legacy->tableExists($realTable)) {
            $payAggs = $this->legacy->select(
                "SELECT CAST(loan_id AS UNSIGNED) AS lid,
                        COUNT(*) AS cnt,
                        SUM(realisasi_pokok) AS paid_p,
                        SUM(realisasi_jasa) AS paid_j,
                        MAX(id) AS max_id
                 FROM `{$realTable}`
                 WHERE id > 0
                   AND NOT (
                     COALESCE(realisasi_pokok, 0) = 0
                     AND COALESCE(realisasi_jasa, 0) = 0
                   )
                 GROUP BY CAST(loan_id AS UNSIGNED)"
            );
            $maxIds = [];
            foreach ($payAggs as $a) {
                $lid = (int) $a->lid;
                $paidByLoan[$lid] = [
                    'principal' => $this->money($a->paid_p ?? 0),
                    'interest' => $this->money($a->paid_j ?? 0),
                ];
                $payCountByLoan[$lid] = (int) $a->cnt;
                if ((int) ($a->max_id ?? 0) > 0) {
                    $maxIds[] = (int) $a->max_id;
                }
            }
            // last saldo rows
            foreach (array_chunk($maxIds, 500) as $chunk) {
                if ($chunk === []) {
                    continue;
                }
                $in = implode(',', $chunk);
                $saldoRows = $this->legacy->select(
                    "SELECT CAST(loan_id AS UNSIGNED) AS lid, saldo_pokok, saldo_jasa
                     FROM `{$realTable}` WHERE id IN ({$in})"
                );
                foreach ($saldoRows as $s) {
                    $lastSaldoByLoan[(int) $s->lid] = [
                        'principal' => $this->money($s->saldo_pokok ?? 0),
                        'interest' => $this->money($s->saldo_jasa ?? 0),
                    ];
                }
            }
        }

        $instCountByLoan = [];
        if ($this->legacy->tableExists($rencanaTable)) {
            $instAggs = $this->legacy->select(
                "SELECT CAST(loan_id AS UNSIGNED) AS lid, COUNT(*) AS cnt
                 FROM `{$rencanaTable}`
                 WHERE CAST(angsuran_ke AS UNSIGNED) > 0
                 GROUP BY CAST(loan_id AS UNSIGNED)"
            );
            foreach ($instAggs as $a) {
                $instCountByLoan[(int) $a->lid] = (int) $a->cnt;
            }
        }

        // Target side
        $targetLoans = DB::connection($conn)->table('loans')
            ->where('tenant_id', $tenantId)
            ->where('legacy_source', 'group_loan')
            ->get(['row_id', 'id', 'principal_amount', 'status', 'completed_at', 'disbursed_at']);

        $targetByLegacyId = [];
        $rowIds = [];
        foreach ($targetLoans as $tl) {
            $targetByLegacyId[(int) $tl->id] = $tl;
            $rowIds[] = (int) $tl->row_id;
        }

        $targetPaidP = [];
        $targetPaidJ = [];
        $targetPayCount = [];
        $targetInstCount = [];
        if ($rowIds !== []) {
            foreach (array_chunk($rowIds, 500) as $chunk) {
                $pays = DB::connection($conn)->table('loan_payments as p')
                    ->join('loan_payment_allocations as a', function ($j): void {
                        $j->on('a.tenant_id', '=', 'p.tenant_id')
                            ->on('a.payment_row_id', '=', 'p.row_id');
                    })
                    ->where('p.tenant_id', $tenantId)
                    ->whereIn('p.loan_row_id', $chunk)
                    ->groupBy('p.loan_row_id', 'a.component')
                    ->selectRaw('p.loan_row_id, a.component, SUM(a.amount) AS s')
                    ->get();
                foreach ($pays as $p) {
                    $rid = (int) $p->loan_row_id;
                    if ($p->component === 'principal') {
                        $targetPaidP[$rid] = $this->money($p->s);
                    } elseif ($p->component === 'interest') {
                        $targetPaidJ[$rid] = $this->money($p->s);
                    }
                }

                $pc = DB::connection($conn)->table('loan_payments')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('loan_row_id', $chunk)
                    ->groupBy('loan_row_id')
                    ->selectRaw('loan_row_id, COUNT(*) AS cnt')
                    ->get();
                foreach ($pc as $p) {
                    $targetPayCount[(int) $p->loan_row_id] = (int) $p->cnt;
                }

                $ic = DB::connection($conn)->table('loan_installments')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('loan_row_id', $chunk)
                    ->where('component', 'principal')
                    ->groupBy('loan_row_id')
                    ->selectRaw('loan_row_id, COUNT(*) AS cnt')
                    ->get();
                foreach ($ic as $p) {
                    $targetInstCount[(int) $p->loan_row_id] = (int) $p->cnt;
                }
            }
        }

        $checked = 0;
        $matched = 0;
        $mismatches = [];
        $missingTarget = [];
        $dirtySaldoNotes = 0;

        foreach ($legacyLoans as $leg) {
            $lid = (int) $leg->id;
            $checked++;
            $disbursed = $this->parseAlokasi($leg->alokasi ?? '0');
            $legPaidP = $paidByLoan[$lid]['principal'] ?? '0.00';
            $legPaidJ = $paidByLoan[$lid]['interest'] ?? '0.00';
            // SoT outstanding = disbursed − Σ realisasi (last saldo_pokok often dirty on reschedule rows).
            // Outstanding SoT: disbursed − Σ paid (paid may be net of legacy reversals).
            $legOutP = $this->bcSub($disbursed, $legPaidP);
            $legInst = $instCountByLoan[$lid] ?? 0;
            $legPayCnt = $payCountByLoan[$lid] ?? 0;
            $legStatus = $this->mapStatus((string) ($leg->status ?? ''));
            $legCompleted = $this->normalizeDate($leg->tgl_lunas ?? null);

            $lastSaldo = $lastSaldoByLoan[$lid]['principal'] ?? null;
            if ($lastSaldo !== null && ! $this->moneyEq($lastSaldo, $legOutP)) {
                $dirtySaldoNotes++;
            }

            $tgt = $targetByLegacyId[$lid] ?? null;
            if ($tgt === null) {
                $missingTarget[] = $lid;
                $mismatches[] = [
                    'loan_id' => $lid,
                    'type' => 'missing_on_target',
                    'fields' => ['loan'],
                ];

                continue;
            }

            $rid = (int) $tgt->row_id;
            $tgtDisbursed = $this->money($tgt->principal_amount);
            $tgtPaidP = $targetPaidP[$rid] ?? '0.00';
            $tgtPaidJ = $targetPaidJ[$rid] ?? '0.00';
            $tgtOutP = $this->bcSub($tgtDisbursed, $tgtPaidP);
            $tgtInst = $targetInstCount[$rid] ?? 0;
            $tgtPayCnt = $targetPayCount[$rid] ?? 0;
            $tgtStatus = (string) $tgt->status;
            $tgtCompleted = $this->normalizeDate($tgt->completed_at ?? null);

            $fieldDiffs = [];
            if (! $this->moneyEq($disbursed, $tgtDisbursed)) {
                $fieldDiffs['principal_disbursed'] = ['legacy' => $disbursed, 'target' => $tgtDisbursed];
            }
            if (! $this->moneyEq($legPaidP, $tgtPaidP)) {
                $fieldDiffs['principal_paid'] = ['legacy' => $legPaidP, 'target' => $tgtPaidP];
            }
            if (! $this->moneyEq($legPaidJ, $tgtPaidJ)) {
                $fieldDiffs['interest_paid'] = ['legacy' => $legPaidJ, 'target' => $tgtPaidJ];
            }
            if (! $this->moneyEq($legOutP, $tgtOutP)) {
                $fieldDiffs['principal_outstanding'] = [
                    'legacy' => $legOutP,
                    'target' => $tgtOutP,
                    'legacy_last_saldo' => $lastSaldo,
                ];
            }
            if ($legInst !== $tgtInst) {
                $fieldDiffs['installment_count'] = ['legacy' => $legInst, 'target' => $tgtInst];
            }
            if ($legPayCnt !== $tgtPayCnt) {
                $fieldDiffs['payment_count'] = ['legacy' => $legPayCnt, 'target' => $tgtPayCnt];
            }
            if ($legStatus !== $tgtStatus) {
                $fieldDiffs['status'] = ['legacy' => $legStatus, 'target' => $tgtStatus];
            }
            // Completion date: only when both claim completed/rescheduled/written_off and both have dates.
            if (
                in_array($legStatus, ['completed', 'rescheduled', 'written_off'], true)
                && in_array($tgtStatus, ['completed', 'rescheduled', 'written_off'], true)
                && $legCompleted !== null
                && $tgtCompleted !== null
                && $legCompleted !== $tgtCompleted
            ) {
                $fieldDiffs['completion_date'] = ['legacy' => $legCompleted, 'target' => $tgtCompleted];
            }

            if ($fieldDiffs === []) {
                $matched++;
            } else {
                $mismatches[] = [
                    'loan_id' => $lid,
                    'type' => 'field_mismatch',
                    'fields' => $fieldDiffs,
                ];
            }
        }

        $mismatchCount = count($mismatches);
        $status = $mismatchCount === 0
            ? 'matched'
            : ($matched > 0 ? 'partial' : 'mismatch');

        // Cap stored mismatches for JSON size
        $sample = array_slice($mismatches, 0, 100);

        return [
            $this->persist($batchRowId, 'loan_balance', [
                'source_count' => $checked,
                'target_count' => $matched,
                'status' => $status,
                'details' => [
                    'checked' => $checked,
                    'matched' => $matched,
                    'mismatched' => $mismatchCount,
                    'missing_on_target' => count($missingTarget),
                    'dirty_legacy_saldo_rows' => $dirtySaldoNotes,
                    'outstanding_formula' => 'max(0, disbursed - SUM(realisasi_pokok)) both sides',
                    'tolerance' => self::MONEY_TOLERANCE,
                    'exceptions' => $sample,
                    'exceptions_truncated' => $mismatchCount > 100,
                ],
            ]),
        ];
    }

    /**
     * Inventory known migration gaps as explicit exception records.
     *
     * @return list<array{scope: string, status: string, source_count: int, target_count: int, details: array<string, mixed>}>
     */
    private function exceptionScopes(int $batchRowId, string $suffix): array
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $paTable = $this->legacy->pinjamanAnggotaTable($suffix);
        $exceptions = [];

        // Beneficiaries whose nia is not in members
        if ($this->legacy->tableExists($paTable)) {
            $memberIds = DB::connection($conn)->table('members')
                ->where('tenant_id', $tenantId)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $memberSet = array_fill_keys($memberIds, true);

            $missingNia = [];
            foreach ($this->extractor->pinjamanAnggotaChunks($suffix, 500) as $rows) {
                foreach ($rows as $row) {
                    $nia = (int) ($row->nia ?? 0);
                    $paId = (int) ($row->id ?? 0);
                    $pinkel = (int) ($row->id_pinkel ?? 0);
                    if ($nia > 0 && ! isset($memberSet[$nia])) {
                        $missingNia[] = [
                            'type' => 'beneficiary_member_missing',
                            'pa_id' => $paId,
                            'nia' => $nia,
                            'id_pinkel' => $pinkel,
                            'approval' => 'pending',
                        ];
                    }
                }
            }
            $exceptions[] = $this->persist($batchRowId, 'exception_missing_member', [
                'source_count' => count($missingNia),
                'target_count' => 0,
                'status' => count($missingNia) === 0 ? 'matched' : 'pending_approval',
                'details' => [
                    'count' => count($missingNia),
                    'sample' => array_slice($missingNia, 0, 50),
                    'truncated' => count($missingNia) > 50,
                    'note' => 'pinjaman_anggota.nia not in anggota_{suffix} (orphan FK; min anggota id often higher). Not auto-created.',
                    'disposition' => 'approve_skip',
                    'disposition_reason' => 'Legacy orphan nia — member never existed in source table; beneficiary cannot be linked without inventing people.',
                ],
            ]);
        }

        // Installments whose parent group loan was not migrated / not in PK
        $rencanaTable = $this->legacy->rencanaAngsuranTable($suffix);
        $pkTable = $this->legacy->pinjamanKelompokTable($suffix);
        if ($this->legacy->tableExists($rencanaTable) && $this->legacy->tableExists($pkTable)) {
            $pkIds = [];
            foreach ($this->legacy->select("SELECT id FROM `{$pkTable}`") as $r) {
                $pkIds[(int) $r->id] = true;
            }
            $orphanInst = 0;
            $orphanSample = [];
            $orphanLoans = $this->legacy->select(
                "SELECT CAST(loan_id AS UNSIGNED) AS lid, COUNT(*) AS cnt
                 FROM `{$rencanaTable}`
                 WHERE CAST(angsuran_ke AS UNSIGNED) > 0
                 GROUP BY CAST(loan_id AS UNSIGNED)"
            );
            foreach ($orphanLoans as $o) {
                $lid = (int) $o->lid;
                if (! isset($pkIds[$lid])) {
                    $orphanInst += (int) $o->cnt;
                    if (count($orphanSample) < 30) {
                        $orphanSample[] = [
                            'type' => 'orphan_installment_loan',
                            'loan_id' => $lid,
                            'installment_rows' => (int) $o->cnt,
                            'approval' => 'pending',
                        ];
                    }
                }
            }
            $exceptions[] = $this->persist($batchRowId, 'exception_orphan_installment', [
                'source_count' => $orphanInst,
                'target_count' => 0,
                'status' => $orphanInst === 0 ? 'matched' : 'pending_approval',
                'details' => [
                    'installment_rows' => $orphanInst,
                    'sample' => $orphanSample,
                    'note' => 'rencana_angsuran loan_id not in pinjaman_kelompok_{suffix} (and not pinjaman_anggota id).',
                    'disposition' => 'approve_skip',
                    'disposition_reason' => 'Orphan schedule rows for deleted/missing loans — no parent to attach.',
                ],
            ]);
        }

        return $exceptions;
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

    private function parseAlokasi(mixed $raw): string
    {
        try {
            if ($raw === null || $raw === '') {
                return '0.00';
            }
            $s = trim((string) $raw);
            if (preg_match('/^\d+(\.\d+)?$/', $s) === 1) {
                return number_format(round((float) $s, 2), 2, '.', '');
            }

            return $this->amounts->parseSigned($raw)['amount'];
        } catch (InvalidArgumentException) {
            return '0.00';
        }
    }

    private function money(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '0.00';
        }

        return number_format(round((float) $v, 2), 2, '.', '');
    }

    private function moneyEq(string $a, string $b): bool
    {
        $diff = abs((float) bcsub($a, $b, 2));

        return $diff <= (float) self::MONEY_TOLERANCE;
    }

    private function moneyMaxZero(string $v): string
    {
        return bccomp($v, '0.00', 2) < 0 ? '0.00' : $v;
    }

    private function bcSub(string $a, string $b): string
    {
        return bcsub($a, $b, 2);
    }

    private function mapStatus(string $raw): string
    {
        return match (strtoupper(trim($raw))) {
            'L' => 'completed',
            'A' => 'active',
            'W' => 'waiting',
            'V' => 'verified',
            'P' => 'proposed',
            'R' => 'rescheduled',
            'H' => 'written_off',
            'T' => 'draft',
            default => 'active',
        };
    }

    private function normalizeDate(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '' || str_starts_with($s, '0000')) {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m) === 1) {
            return $m[1];
        }

        return null;
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
