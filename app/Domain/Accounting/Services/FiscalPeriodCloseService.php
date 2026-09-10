<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Tutup buku minimal:
 *  - close / reopen monthly fiscal_periods (blocks journal posting)
 *  - year-end: carry BS closing balances → next-year account_opening_balances
 *    (revenue/expense reset; laba tahun berjalan → CURRENT_EARNINGS opening)
 *
 * Alokasi laba multi-akun (legacy step 2) = out of scope P2.1.
 */
final class FiscalPeriodCloseService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AccountBalanceQuery $balances,
        private readonly TenantSequenceService $sequences,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   months: list<array<string, mixed>>,
     *   open_count: int,
     *   closed_count: int,
     *   draft_journals: int,
     *   next_year: int,
     *   next_year_openings_exist: bool,
     *   can_close_year: bool,
     *   year_end_preview: list<array<string, mixed>>,
     *   net_income: float
     * }
     */
    public function overview(int $year): array
    {
        $this->assertYear($year);
        $this->ensureYearPeriods($year);

        $periods = FiscalPeriod::query()
            ->where('fiscal_year', $year)
            ->orderBy('fiscal_month')
            ->get()
            ->keyBy(fn (FiscalPeriod $p) => (int) $p->fiscal_month);

        $draftByMonth = $this->draftJournalCounts($year);
        $labels = $this->monthLabels();
        $months = [];
        $openCount = 0;
        $closedCount = 0;

        for ($m = 1; $m <= 12; $m++) {
            /** @var FiscalPeriod|null $period */
            $period = $periods->get($m);
            $status = $period ? (string) $period->status : 'missing';
            if ($status === 'open') {
                $openCount++;
            }
            if ($status === 'closed') {
                $closedCount++;
            }
            $drafts = (int) ($draftByMonth[$m] ?? 0);
            $months[] = [
                'month' => $m,
                'label' => $labels[$m],
                'row_id' => $period ? (int) $period->row_id : null,
                'status' => $status,
                'starts_at' => $period?->starts_at?->format('Y-m-d'),
                'ends_at' => $period?->ends_at?->format('Y-m-d'),
                'closed_at' => $period?->closed_at?->format('Y-m-d H:i'),
                'draft_journals' => $drafts,
                'can_close' => $status === 'open' && $drafts === 0,
                'can_reopen' => $status === 'closed',
            ];
        }

        $asOf = CarbonImmutable::create($year, 12, 31)->startOfDay();
        $netIncome = $this->balances->netIncome($asOf);
        $preview = $this->yearEndOpeningPreview($year);
        $nextYear = $year + 1;
        $nextOpenings = DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $this->context->id())
            ->where('fiscal_year', $nextYear)
            ->where('source', 'year_close')
            ->count();

        return [
            'year' => $year,
            'months' => $months,
            'open_count' => $openCount,
            'closed_count' => $closedCount,
            'draft_journals' => array_sum($draftByMonth),
            'next_year' => $nextYear,
            'next_year_openings_exist' => $nextOpenings > 0,
            'can_close_year' => $openCount === 0 || ($openCount > 0 && array_sum($draftByMonth) === 0),
            'year_end_preview' => $preview,
            'net_income' => $netIncome,
        ];
    }

    public function closeMonth(int $year, int $month, int $userId): FiscalPeriod
    {
        $this->assertYear($year);
        $this->assertMonth($month);

        return DB::connection('tenant')->transaction(function () use ($year, $month, $userId): FiscalPeriod {
            /** @var FiscalPeriod $period */
            $period = FiscalPeriod::query()
                ->where('fiscal_year', $year)
                ->where('fiscal_month', $month)
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->status === 'closed') {
                throw new DomainException("Periode {$year}-{$month} sudah ditutup.");
            }

            $drafts = $this->draftCountForPeriod($period);
            if ($drafts > 0) {
                throw new DomainException(
                    "Masih ada {$drafts} jurnal draft di periode ini. Posting atau hapus dulu."
                );
            }

            $period->status = 'closed';
            $period->closed_at = now();
            $period->closed_by_user_id = $userId;
            $period->save();

            return $period->fresh() ?? $period;
        }, 5);
    }

    public function reopenMonth(int $year, int $month): FiscalPeriod
    {
        $this->assertYear($year);
        $this->assertMonth($month);

        return DB::connection('tenant')->transaction(function () use ($year, $month): FiscalPeriod {
            /** @var FiscalPeriod $period */
            $period = FiscalPeriod::query()
                ->where('fiscal_year', $year)
                ->where('fiscal_month', $month)
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->status !== 'closed') {
                throw new DomainException("Periode {$year}-{$month} belum ditutup.");
            }

            // Block reopen if a later month in same year is still closed? Allow free reopen for ops recovery.
            $period->status = 'open';
            $period->closed_at = null;
            $period->closed_by_user_id = null;
            $period->save();

            return $period->fresh() ?? $period;
        }, 5);
    }

    /**
     * Close remaining open months of $year, ensure next-year periods, write openings.
     *
     * @return array{closed_months: int, openings_written: int, next_year: int, net_income: float}
     */
    public function closeYear(int $year, int $userId, bool $forceRewriteOpenings = false): array
    {
        $this->assertYear($year);
        $this->ensureYearPeriods($year);
        $this->ensureYearPeriods($year + 1);

        return DB::connection('tenant')->transaction(function () use ($year, $userId, $forceRewriteOpenings): array {
            $closedMonths = 0;
            for ($m = 1; $m <= 12; $m++) {
                /** @var FiscalPeriod $period */
                $period = FiscalPeriod::query()
                    ->where('fiscal_year', $year)
                    ->where('fiscal_month', $m)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($period->status === 'open') {
                    $drafts = $this->draftCountForPeriod($period);
                    if ($drafts > 0) {
                        throw new DomainException(
                            "Bulan {$m}/{$year} masih punya {$drafts} jurnal draft."
                        );
                    }
                    $period->status = 'closed';
                    $period->closed_at = now();
                    $period->closed_by_user_id = $userId;
                    $period->save();
                    $closedMonths++;
                }
            }

            $asOf = CarbonImmutable::create($year, 12, 31)->startOfDay();
            $netIncome = $this->balances->netIncome($asOf);
            $written = $this->writeNextYearOpenings($year, $forceRewriteOpenings);

            return [
                'closed_months' => $closedMonths,
                'openings_written' => $written,
                'next_year' => $year + 1,
                'net_income' => $netIncome,
            ];
        }, 5);
    }

    /**
     * @return list<array{code: string, name: string, account_type: string, debit: float, credit: float, signed: float}>
     */
    public function yearEndOpeningPreview(int $year): array
    {
        $asOf = CarbonImmutable::create($year, 12, 31)->startOfDay();
        $rows = [];

        $bsAccounts = Account::query()
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        $earningsCode = AccountBalanceQuery::CURRENT_EARNINGS_CODE;
        $hasEarnings = false;

        foreach ($bsAccounts as $account) {
            $code = (string) $account->code;
            if ($code === $earningsCode) {
                $hasEarnings = true;
                $signed = $this->balances->netIncome($asOf);
            } else {
                $signed = $this->balances->asOfRaw($account, $asOf)['signed'];
            }
            if (abs($signed) < 0.005) {
                continue;
            }
            $cols = $this->balances->splitToColumns($account, $signed);
            $rows[] = [
                'code' => $code,
                'name' => (string) $account->name,
                'account_type' => (string) $account->account_type,
                'debit' => $cols['debit'],
                'credit' => $cols['credit'],
                'signed' => $signed,
            ];
        }

        if (! $hasEarnings) {
            $ni = $this->balances->netIncome($asOf);
            if (abs($ni) >= 0.005) {
                $rows[] = [
                    'code' => $earningsCode,
                    'name' => 'Laba/Rugi Tahun Berjalan',
                    'account_type' => 'equity',
                    'debit' => $ni < 0 ? abs($ni) : 0.0,
                    'credit' => $ni >= 0 ? $ni : 0.0,
                    'signed' => $ni,
                ];
            }
        }

        return $rows;
    }

    private function writeNextYearOpenings(int $year, bool $force): int
    {
        $tenantId = $this->context->id();
        $nextYear = $year + 1;
        $conn = DB::connection('tenant');
        $now = now()->format('Y-m-d H:i:s');

        // Hitung hanya row 'year_close' — 'manual' & 'migration' adalah otoritas
        // masing-masing dan TIDAK di-overwrite (di-handle di upsertOpening()).
        $existing = $conn->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->where('fiscal_year', $nextYear)
            ->where('source', 'year_close')
            ->count();

        if ($existing > 0 && ! $force) {
            throw new DomainException(
                "Saldo awal {$nextYear} dari tutup buku sudah ada. Centang paksa tulis ulang bila perlu."
            );
        }

        if ($force) {
            // Hanya hapus row sumber tutup buku, BUKAN row manual/migration.
            $conn->table('account_opening_balances')
                ->where('tenant_id', $tenantId)
                ->where('fiscal_year', $nextYear)
                ->where('source', 'year_close')
                ->delete();
        }

        $asOf = CarbonImmutable::create($year, 12, 31)->startOfDay();
        $accounts = Account::query()
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->where('is_postable', true)
            ->where('is_active', true)
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        $earningsCode = AccountBalanceQuery::CURRENT_EARNINGS_CODE;
        $written = 0;

        foreach ($accounts as $account) {
            $code = (string) $account->code;
            $signed = $code === $earningsCode
                ? $this->balances->netIncome($asOf)
                : $this->balances->asOfRaw($account, $asOf)['signed'];

            if (abs($signed) < 0.005) {
                continue;
            }

            $cols = $this->balances->splitToColumns($account, $signed);
            $this->upsertOpening(
                $conn,
                $tenantId,
                (int) $account->row_id,
                $nextYear,
                $cols['debit'],
                $cols['credit'],
                $now,
            );
            $written++;
        }

        // If earnings COA row missing, still no row to write — reports compute NI live.
        return $written;
    }

    private function upsertOpening(
        ConnectionInterface $conn,
        int $tenantId,
        int $accountRowId,
        int $year,
        float $debit,
        float $credit,
        string $now,
    ): void {
        $existing = $conn->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->where('account_row_id', $accountRowId)
            ->where('fiscal_year', $year)
            ->lockForUpdate()
            ->first(['row_id', 'source']);

        if ($existing !== null) {
            // JANGAN overwrite row 'manual' (admin onboarding) atau 'migration' (legacy).
            // Hanya izinkan overwrite row 'year_close' (yang memang produk tutup buku).
            if ($existing->source !== 'year_close') {
                return;
            }

            $conn->table('account_opening_balances')
                ->where('tenant_id', $tenantId)
                ->where('row_id', $existing->row_id)
                ->update([
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                    'source' => 'year_close',
                    'updated_at' => $now,
                ]);

            return;
        }

        $conn->table('account_opening_balances')->insert([
            'tenant_id' => $tenantId,
            'id' => $this->sequences->next('account_opening_balances'),
            'account_row_id' => $accountRowId,
            'fiscal_year' => $year,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'source' => 'year_close',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function ensureYearPeriods(int $year): int
    {
        $created = 0;
        for ($month = 1; $month <= 12; $month++) {
            $exists = FiscalPeriod::query()
                ->where('fiscal_year', $year)
                ->where('fiscal_month', $month)
                ->exists();
            if ($exists) {
                continue;
            }
            $starts = CarbonImmutable::create($year, $month, 1)->startOfMonth();
            FiscalPeriod::query()->create([
                'fiscal_year' => $year,
                'fiscal_month' => $month,
                'starts_at' => $starts->toDateString(),
                'ends_at' => $starts->endOfMonth()->toDateString(),
                'status' => 'open',
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * @return array<int, int> month => draft count
     */
    private function draftJournalCounts(int $year): array
    {
        $tenantId = $this->context->id();
        $from = sprintf('%04d-01-01', $year);
        $until = sprintf('%04d-01-01', $year + 1);

        $isSqlite = DB::connection('tenant')->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', transaction_date) AS INTEGER)" : 'MONTH(transaction_date)';

        $rows = DB::connection('tenant')
            ->table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->where('transaction_date', '>=', $from)
            ->where('transaction_date', '<', $until)
            ->selectRaw("{$monthExpr} AS m, COUNT(*) AS c")
            ->groupByRaw($monthExpr)
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->m] = (int) $r->c;
        }

        return $map;
    }

    private function draftCountForPeriod(FiscalPeriod $period): int
    {
        return (int) DB::connection('tenant')
            ->table('journal_entries')
            ->where('tenant_id', $this->context->id())
            ->where('status', 'draft')
            ->whereDate('transaction_date', '>=', $period->starts_at->toDateString())
            ->whereDate('transaction_date', '<=', $period->ends_at->toDateString())
            ->count();
    }

    private function assertYear(int $year): void
    {
        if ($year < 2000 || $year > 2100) {
            throw new DomainException('Tahun tidak valid.');
        }
    }

    private function assertMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            throw new DomainException('Bulan tidak valid.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function monthLabels(): array
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }
}
