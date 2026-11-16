<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Migration\Accounting\DTO\NormalizedJournal;
use App\Domain\Migration\Accounting\DTO\NormalizedOpening;
use App\Domain\Migration\Support\LegacyAmountParser;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
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
        $targetCode = $this->targetCodeFor($code);
        if ($targetCode === null) {
            return null;
        }

        $this->loadAccounts();
        $row = $this->accountsByCode[$targetCode] ?? null;
        if ($row === null) {
            $row = $this->autoProvisionAccount($targetCode);
        }
        if ($row === null || ! $row['is_postable']) {
            return null;
        }

        return $row;
    }

    /** @return array{row_id: int, is_postable: bool}|null */
    private function resolveAny(string $code): ?array
    {
        $targetCode = $this->targetCodeFor($code);
        if ($targetCode === null) {
            return null;
        }

        $this->loadAccounts();
        $row = $this->accountsByCode[$targetCode] ?? null;
        if ($row === null) {
            $row = $this->autoProvisionAccount($targetCode);
        }

        return $row;
    }

    /** @return array{row_id: int, is_postable: bool}|null */
    private function autoProvisionAccount(string $code): ?array
    {
        if (! $this->looksLikeChartCode($code)) {
            return null;
        }

        $level = $this->levelForCode($code);
        $type = $this->accountTypeForCode($code);
        $normal = $this->normalBalanceForType($type);
        $rowId = $this->ensureAccount($code, $level, $type, $normal);
        if ($rowId === null) {
            return null;
        }

        return [
            'row_id' => $rowId,
            'is_postable' => $level === 4,
        ];
    }

    /**
     * Create a standard account and any missing parent levels. Legacy suffixes
     * sometimes reference leaves whose level-3 parent was never seeded (e.g.
     * 4.1.03.01) or use old 3-digit roots (221.03 / 413.01). Without this,
     * journals post to orphan level-4 rows that never appear in the reports.
     */
    private function ensureAccount(string $code, int $level, string $type, string $normal): ?int
    {
        $this->loadAccounts();
        if (isset($this->accountsByCode[$code])) {
            return $this->accountsByCode[$code]['row_id'];
        }

        $parentCode = $this->parentCodeFor($code, $level);
        $parentRowId = null;
        if ($parentCode !== null) {
            $parentRowId = $this->ensureAccount(
                $parentCode,
                $this->levelForCode($parentCode),
                $type,
                $normal,
            );
        }

        $isPostable = $level === 4;

        try {
            $account = Account::query()->create([
                'code' => $code,
                'name' => "Akun Legacy {$code}",
                'account_type' => $type,
                'normal_balance' => $normal,
                'level' => $level,
                'is_postable' => $isPostable,
                'is_active' => true,
                'parent_row_id' => $parentRowId,
                'legacy_parent_code' => $parentCode,
            ]);

            $this->accountsByCode[$code] = [
                'row_id' => (int) $account->row_id,
                'is_postable' => $isPostable,
            ];

            return (int) $account->row_id;
        } catch (\Throwable) {
            // Another process may have created the same code; reuse it.
            $tenantId = $this->context->id();
            $conn = (string) config('tenancy.tenant_connection', 'tenant');
            $existing = DB::connection($conn)->table('accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->first(['row_id', 'is_postable']);
            if ($existing === null) {
                return null;
            }

            $this->accountsByCode[$code] = [
                'row_id' => (int) $existing->row_id,
                'is_postable' => (bool) $existing->is_postable,
            ];

            return (int) $existing->row_id;
        }
    }

    /**
     * Repair old migration artifacts: rename non-standard 3-digit codes to a
     * standard 4-level hierarchy and attach orphan leaves to their parents.
     *
     * @return int number of accounts repaired
     */
    public function repairAccountHierarchy(): int
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $repaired = 0;

        $this->loadAccounts();

        $rows = DB::connection($conn)->table('accounts')
            ->where('tenant_id', $tenantId)
            ->get(['row_id', 'code', 'name', 'level', 'account_type', 'normal_balance', 'parent_row_id']);

        foreach ($rows as $row) {
            $legacyCode = (string) $row->code;
            if ($this->isStandardCode($legacyCode)) {
                continue;
            }
            $targetCode = $this->targetCodeFor($legacyCode);
            if ($targetCode === null || $targetCode === $legacyCode) {
                continue;
            }
            if (isset($this->accountsByCode[$targetCode])) {
                continue;
            }

            DB::connection($conn)->table('accounts')
                ->where('tenant_id', $tenantId)
                ->where('row_id', $row->row_id)
                ->update(['code' => $targetCode, 'updated_at' => now()]);
            $row->code = $targetCode;
            $repaired++;
        }

        $this->accountsByCode = null;
        $this->loadAccounts();

        $orphans = DB::connection($conn)->table('accounts')
            ->where('tenant_id', $tenantId)
            ->whereNull('parent_row_id')
            ->where('level', '>', 1)
            ->orderBy('level')
            ->get(['row_id', 'code', 'level', 'account_type', 'normal_balance']);

        foreach ($orphans as $row) {
            $code = (string) $row->code;
            $level = (int) $row->level;
            $parentCode = $this->parentCodeFor($code, $level);
            if ($parentCode === null) {
                continue;
            }

            $parentRowId = $this->ensureAccount(
                $parentCode,
                $this->levelForCode($parentCode),
                (string) $row->account_type,
                (string) $row->normal_balance,
            );
            if ($parentRowId === null) {
                continue;
            }

            DB::connection($conn)->table('accounts')
                ->where('tenant_id', $tenantId)
                ->where('row_id', $row->row_id)
                ->update([
                    'parent_row_id' => $parentRowId,
                    'legacy_parent_code' => $parentCode,
                    'updated_at' => now(),
                ]);
            $repaired++;
        }

        return $repaired;
    }

    /**
     * Reports hide accounts whose created_at is after the report period. A
     * migrated tenant gets its chart created today, so historical reports
     * (e.g. 2023 balance sheet) would otherwise be empty. Backdate the chart to
     * the first legacy journal/opening period for audit-accurate reporting.
     */
    public function backdateAccountsToLegacyHistory(): int
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');

        $earliest = null;
        $firstJournal = DB::connection($conn)->table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->min('transaction_date');
        if (is_string($firstJournal) && $firstJournal !== '') {
            $earliest = CarbonImmutable::parse($firstJournal)->startOfDay();
        }

        $firstOpeningYear = DB::connection($conn)->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->min('fiscal_year');
        if ($firstOpeningYear !== null) {
            $openingStart = CarbonImmutable::create((int) $firstOpeningYear, 1, 1)->startOfDay();
            if ($earliest === null || $openingStart->lt($earliest)) {
                $earliest = $openingStart;
            }
        }

        if ($earliest === null) {
            return 0;
        }

        return DB::connection($conn)->table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>', $earliest->format('Y-m-d H:i:s'))
            ->update(['created_at' => $earliest->format('Y-m-d H:i:s')]);
    }

    private function targetCodeFor(string $code): ?string
    {
        if (! $this->looksLikeChartCode($code)) {
            return null;
        }
        if ($this->isStandardCode($code)) {
            return $code;
        }

        // 221.03 -> 2.21.03.01, 413.01 -> 4.13.01.01. The original code stays
        // in journal/openings snapshots and in the account name for audit.
        $parts = explode('.', $code);
        $root = (string) array_shift($parts);
        $normalized = array_merge([substr($root, 0, 1), substr($root, 1)], $parts);

        while (count($normalized) < 4) {
            $normalized[] = '01';
        }
        if (count($normalized) > 4) {
            $normalized = [
                $normalized[0],
                $normalized[1],
                $normalized[2],
                $normalized[count($normalized) - 1],
            ];
        }

        return implode('.', $normalized);
    }

    private function parentCodeFor(string $code, int $level): ?string
    {
        if ($level <= 1) {
            return null;
        }

        $parts = explode('.', $code);
        if ($this->isStandardCode($code)) {
            return match ($level) {
                2 => "{$parts[0]}.0.00.00",
                3 => "{$parts[0]}.{$parts[1]}.00.00",
                default => "{$parts[0]}.{$parts[1]}.{$parts[2]}.00",
            };
        }

        $root = (string) ($parts[0] ?? '');
        $normalized = array_merge([substr($root, 0, 1), substr($root, 1)], array_slice($parts, 1));
        if ($level >= 4) {
            $parent = array_slice($normalized, 0, 3);
            while (count($parent) < 3) {
                $parent[] = '00';
            }

            return implode('.', $parent).'.00';
        }
        if ($level === 3) {
            $parent = array_slice($normalized, 0, 2);
            while (count($parent) < 2) {
                $parent[] = '00';
            }

            return implode('.', $parent).'.00.00';
        }

        return substr($root, 0, 1).'.0.00.00';
    }

    private function levelForCode(string $code): int
    {
        $parts = explode('.', $code);
        if (count($parts) < 4) {
            return 4;
        }
        if ($parts[1] === '0') {
            return 1;
        }
        if ($parts[2] === '00' && $parts[3] === '00') {
            return 2;
        }
        if ($parts[3] === '00') {
            return 3;
        }

        return 4;
    }

    private function isStandardCode(string $code): bool
    {
        return (bool) preg_match('/^[1-5]\./', $code);
    }

    private function accountTypeForCode(string $code): string
    {
        return match ((int) substr($code, 0, 1)) {
            1 => 'asset',
            2 => 'liability',
            3 => 'equity',
            4 => 'revenue',
            5 => 'expense',
            default => 'unknown',
        };
    }

    private function normalBalanceForType(string $type): string
    {
        return in_array($type, ['asset', 'expense'], true) ? 'D' : 'C';
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
        // Standard siupk: 1.1.01.01 (lev1 1-5). Some old suffixes also use
        // 3-digit roots such as 221.03 / 413.01. Desa/kec keys like 33.08.19
        // must still be rejected, so only allow standard codes or a 3-digit
        // root before the first dot.
        return (bool) preg_match('/^[1-5](\.\d+){1,3}$/', $code)
            || (bool) preg_match('/^[1-5]\d{2}(\.\d+){1,3}$/', $code);
    }
}
