<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Migration\Accounting\DTO\NormalizedJournal;
use App\Domain\Migration\Accounting\DTO\NormalizedOpening;
use App\Domain\Migration\Support\LegacyAmountParser;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyAccountingNormalizer
{
    /** @var array<string, array{row_id: int, is_postable: bool}>|null */
    private ?array $accountsByCode = null;

    public function __construct(
        private LegacyAmountParser $amounts,
        private TenantContext $context,
    ) {}

    public function warmCaches(): void
    {
        $this->loadAccounts();
    }

    /**
     * @return array{ok: NormalizedJournal|null, error: string|null, skip: bool}
     */
    public function normalizeTransaksi(object $row, string $sourceTable, callable $isMapped): array
    {
        $idt = (int) ($row->idt ?? 0);
        if ($idt <= 0) {
            return ['ok' => null, 'error' => 'Missing idt', 'skip' => false];
        }

        if ($isMapped($sourceTable, (string) $idt)) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $debitCode = trim((string) ($row->rekening_debit ?? ''));
        $creditCode = trim((string) ($row->rekening_kredit ?? ''));
        $date = (string) ($row->tgl_transaksi ?? '');
        $rawAmount = $row->jumlah ?? '';

        if ($debitCode === '' || $creditCode === '' || $debitCode === '-' || $creditCode === '-') {
            // Incomplete legacy transaction row (missing debit/credit account) — skip, do not fail batch.
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $rawStr = is_scalar($rawAmount) ? trim((string) $rawAmount) : '';
        if ($rawStr === '' || $rawStr === '0' || $rawStr === '0.0' || $rawStr === '0.00' || $rawStr === '0,00') {
            // Placeholder / empty legacy rows — skip, do not fail batch.
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        try {
            $parsed = $this->amounts->parseSigned($rawAmount);
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "idt={$idt}: {$e->getMessage()}", 'skip' => false];
        }

        $amount = $parsed['amount'];
        if (bccomp($amount, '0.00', 2) <= 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        // Negative jumlah = reverse: swap debit/credit account sides, keep magnitude.
        if ($parsed['negative']) {
            [$debitCode, $creditCode] = [$creditCode, $debitCode];
        }

        $debit = $this->resolvePostable($debitCode);
        if ($debit === null) {
            return ['ok' => null, 'error' => "idt={$idt}: missing/non-postable debit account [{$debitCode}]", 'skip' => false];
        }
        $credit = $this->resolvePostable($creditCode);
        if ($credit === null) {
            return ['ok' => null, 'error' => "idt={$idt}: missing/non-postable credit account [{$creditCode}]", 'skip' => false];
        }

        if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return ['ok' => null, 'error' => "idt={$idt}: invalid date [{$date}]", 'skip' => false];
        }
        $date = substr($date, 0, 10);

        if (! $this->hasOpenPeriod($date)) {
            return ['ok' => null, 'error' => "idt={$idt}: no open fiscal period for [{$date}]", 'skip' => false];
        }

        $snapshot = [
            'idt' => $idt,
            'tgl_transaksi' => $date,
            'rekening_debit' => $debitCode,
            'rekening_kredit' => $creditCode,
            'jumlah' => is_scalar($rawAmount) ? (string) $rawAmount : json_encode($rawAmount),
            'urutan' => $row->urutan ?? 0,
            'idtp' => $row->idtp ?? 0,
            'id_pinj' => $row->id_pinj ?? 0,
            'id_pinj_i' => $row->id_pinj_i ?? 0,
            'keterangan_transaksi' => $row->keterangan_transaksi ?? null,
            'relasi' => $row->relasi ?? null,
            'id_user' => $row->id_user ?? null,
        ];

        return [
            'ok' => new NormalizedJournal(
                idt: $idt,
                transactionDate: $date,
                sequenceNumber: max(0, (int) ($row->urutan ?? 0)),
                description: (string) ($row->keterangan_transaksi ?? ''),
                legacyTransactionTypeId: (int) ($row->idtp ?? 0),
                legacyLoanId: (int) ($row->id_pinj ?? 0),
                legacyLoanItemId: (int) ($row->id_pinj_i ?? 0),
                legacyRelation: isset($row->relasi) ? (string) $row->relasi : null,
                debitCode: $debitCode,
                creditCode: $creditCode,
                debitAccountRowId: $debit['row_id'],
                creditAccountRowId: $credit['row_id'],
                amount: $amount,
                amountRaw: is_scalar($rawAmount) ? (string) $rawAmount : '',
                snapshot: $snapshot,
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedOpening|null, error: string|null, skip: bool}
     */
    public function normalizeOpening(object $row, string $sourceTable, callable $isMapped): array
    {
        $code = trim((string) ($row->kode_akun ?? ''));
        $year = (int) ($row->tahun ?? 0);
        if ($code === '' || $year < 2000) {
            return ['ok' => null, 'error' => "Invalid opening code/year [{$code}/{$year}]", 'skip' => false];
        }

        $sourceId = "{$year}:{$code}";
        if ($isMapped($sourceTable, $sourceId, '0')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        // Prefer any account by code. Non-COA saldo keys (desa/kd_kec alokasi laba) → skip.
        $account = $this->resolveAny($code);
        if ($account === null) {
            if ($this->looksLikeChartCode($code)) {
                return ['ok' => null, 'error' => "Opening missing account [{$code}] year={$year}", 'skip' => false];
            }

            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        try {
            $debitRaw = $row->debit ?? '0';
            // Legacy column is `kredit` (not `credit`).
            $creditRaw = $row->kredit ?? $row->credit ?? '0';
            $debit = $this->parseNonNegative($debitRaw);
            $credit = $this->parseNonNegative($creditRaw);
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "Opening {$sourceId}: {$e->getMessage()}", 'skip' => false];
        }

        if (bccomp($debit, '0.00', 2) === 0 && bccomp($credit, '0.00', 2) === 0) {
            return ['ok' => null, 'error' => null, 'skip' => true]; // zero opening skip
        }

        return [
            'ok' => new NormalizedOpening(
                accountCode: $code,
                accountRowId: $account['row_id'],
                fiscalYear: $year,
                debit: $debit,
                credit: $credit,
                sourceId: $sourceId,
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    private function parseNonNegative(mixed $raw): string
    {
        if ($raw === null || trim((string) $raw) === '' || (string) $raw === '0') {
            return '0.00';
        }
        try {
            return $this->amounts->parse($raw);
        } catch (InvalidArgumentException $e) {
            // allow zero-like after strip
            $s = trim((string) $raw);
            if ($s === '0' || $s === '0.0' || $s === '0.00' || $s === '0,00') {
                return '0.00';
            }
            throw $e;
        }
    }

    /** @return array{row_id: int, is_postable: bool}|null */
    private function resolvePostable(string $code): ?array
    {
        $this->loadAccounts();
        $row = $this->accountsByCode[$code] ?? null;
        if ($row === null) {
            $row = $this->autoProvisionAccount($code);
        }
        if ($row === null || ! $row['is_postable']) {
            return null;
        }

        return $row;
    }

    /** @return array{row_id: int, is_postable: bool}|null */
    private function resolveAny(string $code): ?array
    {
        $this->loadAccounts();
        $row = $this->accountsByCode[$code] ?? null;
        if ($row === null) {
            $row = $this->autoProvisionAccount($code);
        }

        return $row;
    }

    /** @return array{row_id: int, is_postable: bool}|null */
    private function autoProvisionAccount(string $code): ?array
    {
        if (! $this->looksLikeChartCode($code)) {
            return null;
        }

        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        $parts = explode('.', $code);
        $level = count($parts);
        $parentCode = null;
        if ($level === 4) {
            $parentCode = "{$parts[0]}.{$parts[1]}.{$parts[2]}.00";
        } elseif ($level === 3) {
            $parentCode = "{$parts[0]}.{$parts[1]}.00.00";
        } elseif ($level === 2) {
            $parentCode = "{$parts[0]}.0.00.00";
        }

        $parentRowId = null;
        if ($parentCode !== null) {
            $parentRow = DB::connection($conn)->table('accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', $parentCode)
                ->first(['row_id']);
            if ($parentRow !== null) {
                $parentRowId = (int) $parentRow->row_id;
            }
        }

        $type = match (true) {
            str_starts_with($code, '1.') => 'asset',
            str_starts_with($code, '2.') => 'liability',
            str_starts_with($code, '3.') => 'equity',
            str_starts_with($code, '4.') => 'revenue',
            str_starts_with($code, '5.') => 'expense',
            default => 'unknown',
        };

        $normal = (str_starts_with($code, '1.') || str_starts_with($code, '5.')) ? 'D' : 'C';

        try {
            $account = Account::query()->create([
                'code' => $code,
                'name' => "Akun Legacy {$code}",
                'account_type' => $type,
                'normal_balance' => $normal,
                'level' => $level,
                'is_postable' => ($level === 4),
                'is_active' => true,
                'parent_row_id' => $parentRowId,
                'legacy_parent_code' => $parentCode,
            ]);

            $info = [
                'row_id' => (int) $account->row_id,
                'is_postable' => ($level === 4),
            ];
            $this->accountsByCode[$code] = $info;

            return $info;
        } catch (\Throwable) {
            return null;
        }
    }

    private function loadAccounts(): void
    {
        if ($this->accountsByCode !== null) {
            return;
        }

        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $rows = DB::connection($conn)->table('accounts')
            ->where('tenant_id', $tenantId)
            ->get(['row_id', 'code', 'is_postable']);

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->code] = [
                'row_id' => (int) $row->row_id,
                'is_postable' => (bool) $row->is_postable,
            ];
        }
        $this->accountsByCode = $map;
    }

    private function hasOpenPeriod(string $date): bool
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        return DB::connection($conn)->table('fiscal_periods')
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->exists();
    }

    private function looksLikeChartCode(string $code): bool
    {
        // Standard siupk: 1.1.01.01 (lev1 1-5). Desa/kec keys like 33.08.19 are not COA.
        return (bool) preg_match('/^[1-5](\.\d+){1,3}$/', $code);
    }
}
