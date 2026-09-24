<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Budgeting\Models\Budget;
use App\Domain\Budgeting\Models\BudgetLine;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * E-Budgeting per Triwulan — pembandingingan Rencana Anggaran vs Realisasi
 * per triwulan (Q1, Q2, Q3, Q4) plus Year-to-Date.
 *
 * Sumber data:
 *   - `budget_lines`        — rencana anggaran (per bulan)
 *   - `journal_lines`       — realisasi (postingan jurnal posted)
 *   - `account_opening_balances` — saldo awal tahun (untuk YTD)
 *
 * Catatan:
 *   - Setiap baris akun dipetakan ke 12 bulan.
 *   - Realisasi triwulan = Σ movement (Debit − Kredit) untuk akun C-normal,
 *     atau (Kredit − Debit) untuk akun D-normal, sesuai sign normal balance.
 *   - YTD = Σ movement Jan s/d akhir triwulan.
 *   - Filter kategori = prefix kode akun (4.x, 5.x, atau khusus prefix lain).
 */
final readonly class BudgetingReportService
{
    /** @var list<int> */
    public const QUARTERS = [1, 2, 3, 4];

    /** @var array<int, array{0:int,1:int}> */
    private const QUARTER_MONTHS = [
        1 => [1, 2, 3],
        2 => [4, 5, 6],
        3 => [7, 8, 9],
        4 => [10, 11, 12],
    ];

    public function __construct(
        private AccountBalanceQuery $balances,
    ) {}

    /**
     * @return array{
     *   budget: array{row_id:int,fiscal_year:int,name:string,status:string,approved_at:?string}|null,
     *   period: array{year:int, quarter:int, is_ytd:bool, label:string, range_label:string},
     *   identity: array<string,mixed>,
     *   category_filter: string|null,
     *   categories: list<array{value:string,label:string}>,
     *   rows: list<array{
     *       row_id:int, code:string, name:string, account_type:string,
     *       budget_quarter:float, realized_quarter:float, variance:float, realized_pct:float,
     *       budget_ytd:float, realized_ytd:float, variance_ytd:float, realized_pct_ytd:float
     *   }>,
     *   totals: array{
     *       revenue: array{budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float},
     *       expense: array{budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float},
     *       net: array{budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float}
     *   },
     *   generated_at: string
     * }
     */
    public function build(int $year, int $quarter, ?string $categoryFilter, bool $ytd): array
    {
        if (! $ytd && ! in_array($quarter, self::QUARTERS, true)) {
            $quarter = (int) ceil((int) date('n') / 3);
            if (! in_array($quarter, self::QUARTERS, true)) {
                $quarter = 4;
            }
        }

        $budget = Budget::query()->where('fiscal_year', $year)->first();
        $accounts = $this->budgetableAccounts($categoryFilter);

        $budgetByAccount = $this->budgetAmountsByAccount($budget, $accounts, $quarter);

        $realizedQuarter = $this->realizedMovements($accounts, $year, $quarter, false);
        $realizedYtd = $this->realizedMovements($accounts, $year, $quarter, true);

        $rows = [];
        $totRevenue = $this->emptyTotals();
        $totExpense = $this->emptyTotals();

        foreach ($accounts as $account) {
            $rowId = (int) $account->row_id;
            $budgetQ = (float) ($budgetByAccount['quarter'][$rowId] ?? 0);
            $budgetY = (float) ($budgetByAccount['ytd'][$rowId] ?? 0);

            $realQ = $this->signedMovement($account, $realizedQuarter[$rowId] ?? null);
            $realY = $this->signedMovement($account, $realizedYtd[$rowId] ?? null);

            $row = [
                'row_id' => $rowId,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'account_type' => (string) $account->account_type,
                'budget_quarter' => round($budgetQ, 2),
                'realized_quarter' => round($realQ, 2),
                'variance' => round($realQ - $budgetQ, 2),
                'realized_pct' => $this->percent($realQ, $budgetQ),
                'budget_ytd' => round($budgetY, 2),
                'realized_ytd' => round($realY, 2),
                'variance_ytd' => round($realY - $budgetY, 2),
                'realized_pct_ytd' => $this->percent($realY, $budgetY),
            ];

            $rows[] = $row;

            $bucket = $account->account_type === 'revenue' ? 'revenue' : 'expense';
            if ($bucket === 'revenue') {
                $totRevenue = $this->mergeTotals($totRevenue, $row);
            } else {
                $totExpense = $this->mergeTotals($totExpense, $row);
            }
        }

        $netRow = $this->combineTotals($totRevenue, $totExpense);

        $profile = OrganizationProfile::query()->first();

        return [
            'budget' => $budget ? [
                'row_id' => (int) $budget->row_id,
                'fiscal_year' => (int) $budget->fiscal_year,
                'name' => (string) $budget->name,
                'status' => (string) $budget->status,
                'approved_at' => $budget->approved_at?->toDateTimeString(),
            ] : null,
            'period' => [
                'year' => $year,
                'quarter' => $quarter,
                'is_ytd' => $ytd,
                'label' => $ytd ? "YTD {$year}" : "Q{$quarter} {$year}",
                'range_label' => $ytd
                    ? 'Januari – '.$this->quarterEndLabel($quarter)." {$year}"
                    : $this->quarterRangeLabel($quarter, $year),
            ],
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
            ],
            'category_filter' => $categoryFilter,
            'categories' => $this->categories(),
            'rows' => $rows,
            'totals' => [
                'revenue' => $totRevenue,
                'expense' => $totExpense,
                'net' => $netRow,
            ],
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
        ];
    }

    /* -------------------------------------------------------------- */
    /*                       private helpers */
    /* -------------------------------------------------------------- */

    /**
     * @return Collection<int, Account>
     */
    private function budgetableAccounts(?string $categoryFilter): Collection
    {
        $query = Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code');

        $query = $this->applyCategoryFilter($query, $categoryFilter);

        return $query->get(['row_id', 'code', 'name', 'account_type', 'normal_balance']);
    }

    private function applyCategoryFilter($query, ?string $categoryFilter)
    {
        if ($categoryFilter === null || $categoryFilter === '' || $categoryFilter === 'all') {
            return $query;
        }

        return match ($categoryFilter) {
            'pendapatan' => $query->where('account_type', 'revenue'),
            'beban' => $query->where('account_type', 'expense'),
            'pendapatan_operasional' => $query->where('account_type', 'revenue')->where('code', 'like', '4.1.%'),
            'pendapatan_non_operasional' => $query->where('account_type', 'revenue')->where('code', 'like', '4.2.%'),
            'beban_operasional' => $query->where('account_type', 'expense')->where(function ($q): void {
                $q->where('code', 'like', '5.1.%')->orWhere('code', 'like', '5.2.%');
            }),
            'beban_non_operasional' => $query->where('account_type', 'expense')->where('code', 'like', '5.3.%'),
            'pajak' => $query->where('code', 'like', '5.4.%'),
            default => $query->where('code', 'like', $categoryFilter.'.%'),
        };
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array{
     *   quarter: array<int,float>,
     *   ytd: array<int,float>
     * }
     */
    private function budgetAmountsByAccount(?Budget $budget, Collection $accounts, int $quarter): array
    {
        if ($budget === null) {
            return ['quarter' => [], 'ytd' => []];
        }

        $accountIds = $accounts->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        if ($accountIds === []) {
            return ['quarter' => [], 'ytd' => []];
        }

        $lines = BudgetLine::query()
            ->where('budget_row_id', $budget->row_id)
            ->whereNull('organization_unit_row_id')
            ->whereIn('account_row_id', $accountIds)
            ->get(['account_row_id', 'fiscal_month', 'amount']);

        $quarterMonths = self::QUARTER_MONTHS[$quarter] ?? self::QUARTER_MONTHS[4];
        $qEnd = max($quarterMonths);

        $perAccountMonthly = [];
        foreach ($lines as $line) {
            $accId = (int) $line->account_row_id;
            $m = (int) $line->fiscal_month;
            $perAccountMonthly[$accId][$m] = (float) $line->amount;
        }

        $quarterOut = [];
        $ytdOut = [];
        foreach ($accountIds as $accId) {
            $months = $perAccountMonthly[$accId] ?? [];

            $qSum = 0.0;
            foreach ($quarterMonths as $m) {
                $qSum += (float) ($months[$m] ?? 0);
            }
            $quarterOut[$accId] = round($qSum, 2);

            $ytdSum = 0.0;
            for ($m = 1; $m <= $qEnd; $m++) {
                $ytdSum += (float) ($months[$m] ?? 0);
            }
            $ytdOut[$accId] = round($ytdSum, 2);
        }

        return ['quarter' => $quarterOut, 'ytd' => $ytdOut];
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array{debit: float, credit: float}>
     */
    private function realizedMovements(Collection $accounts, int $year, int $quarter, bool $ytd): array
    {
        $months = $ytd ? [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] : self::QUARTER_MONTHS[$quarter];

        $from = CarbonImmutable::create($year, $months[0], 1)->startOfDay();
        $last = max($months);
        $until = CarbonImmutable::create($year, $last, 1)->endOfMonth()->addDay()->startOfDay();

        $rows = $this->balances->movements($from, $until);
        $result = [];
        foreach ($accounts as $account) {
            $row = $rows->get((int) $account->row_id);
            $result[(int) $account->row_id] = $this->balances->movementPair($row);
        }

        return $result;
    }

    /**
     * @param  array{debit:float,credit:float}|null  $pair
     */
    private function signedMovement(Account $account, ?array $pair): float
    {
        if ($pair === null) {
            return 0.0;
        }
        $normal = (string) ($account->normal_balance ?? 'D');
        $type = (string) ($account->account_type ?? '');

        if ($normal === 'C' || in_array($type, ['liability', 'equity', 'revenue'], true)) {
            return round($pair['credit'] - $pair['debit'], 2);
        }

        return round($pair['debit'] - $pair['credit'], 2);
    }

    /**
     * @return array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }
     */
    private function emptyTotals(): array
    {
        return [
            'budget_quarter' => 0.0,
            'realized_quarter' => 0.0,
            'variance' => 0.0,
            'realized_pct' => 0.0,
            'budget_ytd' => 0.0,
            'realized_ytd' => 0.0,
            'variance_ytd' => 0.0,
            'realized_pct_ytd' => 0.0,
        ];
    }

    /**
     * @param  array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }  $totals
     * @param  array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }  $row
     * @return array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }
     */
    private function mergeTotals(array $totals, array $row): array
    {
        $totals['budget_quarter'] += $row['budget_quarter'];
        $totals['realized_quarter'] += $row['realized_quarter'];
        $totals['variance'] += $row['variance'];
        $totals['budget_ytd'] += $row['budget_ytd'];
        $totals['realized_ytd'] += $row['realized_ytd'];
        $totals['variance_ytd'] += $row['variance_ytd'];

        $totals['realized_pct'] = $this->percent($totals['realized_quarter'], $totals['budget_quarter']);
        $totals['realized_pct_ytd'] = $this->percent($totals['realized_ytd'], $totals['budget_ytd']);

        return $totals;
    }

    /**
     * @param  array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }  $revenue
     * @param  array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }  $expense
     * @return array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }
     */
    private function combineTotals(array $revenue, array $expense): array
    {
        $net = $this->emptyTotals();
        $net['budget_quarter'] = $revenue['budget_quarter'] - $expense['budget_quarter'];
        $net['realized_quarter'] = $revenue['realized_quarter'] - $expense['realized_quarter'];
        $net['variance'] = $net['realized_quarter'] - $net['budget_quarter'];

        $net['budget_ytd'] = $revenue['budget_ytd'] - $expense['budget_ytd'];
        $net['realized_ytd'] = $revenue['realized_ytd'] - $expense['realized_ytd'];
        $net['variance_ytd'] = $net['realized_ytd'] - $net['budget_ytd'];

        $net['realized_pct'] = $this->percent($net['realized_quarter'], $net['budget_quarter']);
        $net['realized_pct_ytd'] = $this->percent($net['realized_ytd'], $net['budget_ytd']);

        return $net;
    }

    private function percent(float $realized, float $budget): float
    {
        if (abs($budget) < 0.005) {
            return $realized > 0 ? 100.0 : 0.0;
        }

        return round(($realized / $budget) * 100.0, 2);
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function categories(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Akun'],
            ['value' => 'pendapatan', 'label' => 'Pendapatan'],
            ['value' => 'beban', 'label' => 'Beban'],
            ['value' => 'pendapatan_operasional', 'label' => 'Pendapatan Operasional (4.1)'],
            ['value' => 'pendapatan_non_operasional', 'label' => 'Pendapatan Non Operasional (4.2)'],
            ['value' => 'beban_operasional', 'label' => 'Beban Operasional (5.1, 5.2)'],
            ['value' => 'beban_non_operasional', 'label' => 'Beban Non Operasional (5.3)'],
            ['value' => 'pajak', 'label' => 'Beban Pajak (5.4)'],
        ];
    }

    private function quarterEndLabel(int $quarter): string
    {
        return match ($quarter) {
            1 => 'Maret',
            2 => 'Juni',
            3 => 'September',
            4 => 'Desember',
            default => 'Desember',
        };
    }

    private function quarterRangeLabel(int $quarter, int $year): string
    {
        return match ($quarter) {
            1 => "Januari – Maret {$year}",
            2 => "April – Juni {$year}",
            3 => "Juli – September {$year}",
            4 => "Oktober – Desember {$year}",
            default => "Tahun {$year}",
        };
    }
}
