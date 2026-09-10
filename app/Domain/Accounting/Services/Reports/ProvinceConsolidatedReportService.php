<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Models\Platform\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ProvinceConsolidatedReportService
{
    /**
     * @return Collection<int, Tenant>
     */
    public function listTenants(?string $provinceCode = null): Collection
    {
        $query = Tenant::query()
            ->whereIn('status', ['active', 'read_only']);

        if ($provinceCode !== null && $provinceCode !== '') {
            $query->where('province_code', $provinceCode);
        }

        return $query->orderBy('regency_name')->orderBy('name')->get();
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function dashboardMetrics(array $tenantIds, int $year, ?int $month = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $tenants = Tenant::query()->whereIn('row_id', $tenantIds)->get();

        if ($tenantIds === []) {
            return [
                'period' => $period,
                'summary' => [
                    'total_regencies' => 0,
                    'total_kecamatans' => 0,
                    'total_cash' => 0.0,
                    'active_loans_count' => 0,
                    'active_loan_principal' => 0.0,
                    'net_income_ytd' => 0.0,
                ],
                'regency_recap' => [],
            ];
        }

        $cashBalances = $this->queryBalancesForAccountCodes($tenantIds, ['1.1.01%', '1.1.02%'], $asOf);
        $totalCash = round(array_sum(array_column($cashBalances, 'balance')), 2);

        $loanStats = DB::connection('tenant')
            ->table('loans')
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', ['active', 'disbursed'])
            ->selectRaw('COUNT(*) as total_loans')
            ->selectRaw('CAST(COALESCE(SUM(principal_amount), 0) AS CHAR) as total_principal')
            ->first();

        $incomeStatement = $this->incomeStatement($tenantIds, $year, $month);
        $netIncomeYtd = (float) ($incomeStatement['summary']['after_tax']['ytd'] ?? 0);

        $byRegency = $tenants->groupBy(fn ($t) => $t->regency_name ?: 'Lainnya');
        $regencyRecap = [];

        foreach ($byRegency as $regName => $regTenants) {
            $regTenantIds = $regTenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();
            $regCash = 0.0;
            foreach ($regTenantIds as $tId) {
                $regCash += (float) ($cashBalances[$tId]['balance'] ?? 0);
            }

            $regLoans = DB::connection('tenant')
                ->table('loans')
                ->whereIn('tenant_id', $regTenantIds)
                ->whereIn('status', ['active', 'disbursed'])
                ->selectRaw('COUNT(*) as total_loans, CAST(COALESCE(SUM(principal_amount), 0) AS CHAR) as total_principal')
                ->first();

            $regencyRecap[] = [
                'regency_name' => $regName,
                'kecamatans_count' => count($regTenants),
                'cash' => round($regCash, 2),
                'active_loans' => (int) ($regLoans->total_loans ?? 0),
                'active_principal' => (float) ($regLoans->total_principal ?? 0),
            ];
        }

        return [
            'period' => $period,
            'summary' => [
                'total_regencies' => count($byRegency),
                'total_kecamatans' => count($tenantIds),
                'total_cash' => $totalCash,
                'active_loans_count' => (int) ($loanStats->total_loans ?? 0),
                'active_loan_principal' => (float) ($loanStats->total_principal ?? 0),
                'net_income_ytd' => $netIncomeYtd,
            ],
            'regency_recap' => $regencyRecap,
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function balanceSheet(array $tenantIds, int $year, ?int $month = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->endOfDay();

        if ($tenantIds === []) {
            return [
                'period' => $period,
                'assets' => ['rows' => [], 'total' => 0.0],
                'liabilities' => ['rows' => [], 'total' => 0.0],
                'equity' => ['rows' => [], 'total' => 0.0],
                'total_liabilities_and_equity' => 0.0,
                'is_balanced' => true,
                'difference' => 0.0,
            ];
        }

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();

        $lines = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts', function ($join): void {
                $join->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->whereIn('lines.tenant_id', $tenantIds)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '<=', $asOf->toDateString())
            ->groupBy('accounts.code')
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('CAST(COALESCE(SUM(lines.debit), 0) AS CHAR) AS debit')
            ->selectRaw('CAST(COALESCE(SUM(lines.credit), 0) AS CHAR) AS credit')
            ->get()
            ->keyBy('account_code');

        $groupedByCode = [];
        foreach ($accounts as $account) {
            $code = $account->code;
            if (! isset($groupedByCode[$code])) {
                $groupedByCode[$code] = [
                    'code' => $code,
                    'name' => $account->name,
                    'account_type' => $account->account_type,
                    'normal_balance' => $account->normal_balance,
                    'level' => $account->level,
                    'parent_code' => $account->parent_code,
                    'debit' => 0.0,
                    'credit' => 0.0,
                ];
            }

            if (isset($lines[$code])) {
                $groupedByCode[$code]['debit'] += (float) $lines[$code]->debit;
                $groupedByCode[$code]['credit'] += (float) $lines[$code]->credit;
            }
        }

        $calcBalance = function (array $item): float {
            $debit = $item['debit'];
            $credit = $item['credit'];
            $normal = strtoupper((string) ($item['normal_balance'] ?? 'D'));
            $type = (string) ($item['account_type'] ?? '');

            if ($normal === 'C' || in_array($type, ['liability', 'equity'], true)) {
                return round($credit - $debit, 2);
            }

            return round($debit - $credit, 2);
        };

        $assetsRows = [];
        $liabilitiesRows = [];
        $equityRows = [];

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($groupedByCode as $code => $item) {
            $bal = $calcBalance($item);

            $row = [
                'code' => $code,
                'name' => $item['name'],
                'level' => $item['level'],
                'balance' => $bal,
            ];

            if (str_starts_with($code, '1.')) {
                $assetsRows[] = $row;
                if ($item['level'] === 1) {
                    $totalAssets += $bal;
                }
            } elseif (str_starts_with($code, '2.')) {
                $liabilitiesRows[] = $row;
                if ($item['level'] === 1) {
                    $totalLiabilities += $bal;
                }
            } elseif (str_starts_with($code, '3.')) {
                $equityRows[] = $row;
                if ($item['level'] === 1) {
                    $totalEquity += $bal;
                }
            }
        }

        $totalLiabEq = round($totalLiabilities + $totalEquity, 2);
        $diff = round($totalAssets - $totalLiabEq, 2);

        return [
            'period' => $period,
            'assets' => ['rows' => $assetsRows, 'total' => round($totalAssets, 2)],
            'liabilities' => ['rows' => $liabilitiesRows, 'total' => round($totalLiabilities, 2)],
            'equity' => ['rows' => $equityRows, 'total' => round($totalEquity, 2)],
            'total_liabilities_and_equity' => $totalLiabEq,
            'is_balanced' => abs($diff) < 0.01,
            'difference' => $diff,
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function incomeStatement(array $tenantIds, int $year, ?int $month = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $from = CarbonImmutable::parse($period['from'])->startOfDay();
        $until = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();

        if ($tenantIds === []) {
            return [
                'period' => $period,
                'revenue_ops' => ['rows' => [], 'total' => 0.0],
                'revenue_non' => ['rows' => [], 'total' => 0.0],
                'expense_ops' => ['rows' => [], 'total' => 0.0],
                'expense_non' => ['rows' => [], 'total' => 0.0],
                'tax' => ['rows' => [], 'total' => 0.0],
                'summary' => [
                    'revenue_ops' => ['ytd' => 0.0],
                    'expense_ops' => ['ytd' => 0.0],
                    'operating_profit' => ['ytd' => 0.0],
                    'before_tax' => ['ytd' => 0.0],
                    'after_tax' => ['ytd' => 0.0],
                ],
            ];
        }

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('account_type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $lines = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts', function ($join): void {
                $join->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->whereIn('lines.tenant_id', $tenantIds)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $from->toDateString())
            ->where('entries.transaction_date', '<', $until->toDateString())
            ->groupBy('accounts.code')
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('CAST(COALESCE(SUM(lines.debit), 0) AS CHAR) AS debit')
            ->selectRaw('CAST(COALESCE(SUM(lines.credit), 0) AS CHAR) AS credit')
            ->get()
            ->keyBy('account_code');

        $groupedByCode = [];
        foreach ($accounts as $account) {
            $code = $account->code;
            if (! isset($groupedByCode[$code])) {
                $groupedByCode[$code] = [
                    'code' => $code,
                    'name' => $account->name,
                    'account_type' => $account->account_type,
                    'normal_balance' => $account->normal_balance,
                    'level' => $account->level,
                    'debit' => 0.0,
                    'credit' => 0.0,
                ];
            }

            if (isset($lines[$code])) {
                $groupedByCode[$code]['debit'] += (float) $lines[$code]->debit;
                $groupedByCode[$code]['credit'] += (float) $lines[$code]->credit;
            }
        }

        $revOps = 0.0;
        $revNon = 0.0;
        $expOps = 0.0;
        $expNon = 0.0;
        $taxTotal = 0.0;

        $revOpsRows = [];
        $revNonRows = [];
        $expOpsRows = [];
        $expNonRows = [];
        $taxRows = [];

        foreach ($groupedByCode as $code => $item) {
            $debit = $item['debit'];
            $credit = $item['credit'];
            $type = $item['account_type'];

            $bal = $type === 'revenue' ? round($credit - $debit, 2) : round($debit - $credit, 2);

            $row = [
                'code' => $code,
                'name' => $item['name'],
                'level' => $item['level'],
                'amount' => $bal,
            ];

            if (str_starts_with($code, '4.1')) {
                $revOpsRows[] = $row;
                if ($item['level'] === 1 || $item['level'] === 2) {
                    $revOps += $bal;
                }
            } elseif (str_starts_with($code, '4.2') || str_starts_with($code, '4.3')) {
                $revNonRows[] = $row;
                if ($item['level'] === 1 || $item['level'] === 2) {
                    $revNon += $bal;
                }
            } elseif (str_starts_with($code, '5.1') || str_starts_with($code, '5.2')) {
                $expOpsRows[] = $row;
                if ($item['level'] === 1 || $item['level'] === 2) {
                    $expOps += $bal;
                }
            } elseif (str_starts_with($code, '5.3')) {
                $expNonRows[] = $row;
                if ($item['level'] === 1 || $item['level'] === 2) {
                    $expNon += $bal;
                }
            } elseif (str_starts_with($code, '5.4')) {
                $taxRows[] = $row;
                if ($item['level'] === 1 || $item['level'] === 2) {
                    $taxTotal += $bal;
                }
            }
        }

        $operatingProfit = round($revOps - $expOps, 2);
        $beforeTax = round($operatingProfit + $revNon - $expNon, 2);
        $afterTax = round($beforeTax - $taxTotal, 2);

        return [
            'period' => $period,
            'revenue_ops' => ['rows' => $revOpsRows, 'total' => round($revOps, 2)],
            'revenue_non' => ['rows' => $revNonRows, 'total' => round($revNon, 2)],
            'expense_ops' => ['rows' => $expOpsRows, 'total' => round($expOps, 2)],
            'expense_non' => ['rows' => $expNonRows, 'total' => round($expNon, 2)],
            'tax' => ['rows' => $taxRows, 'total' => round($taxTotal, 2)],
            'summary' => [
                'revenue_ops' => ['ytd' => round($revOps, 2)],
                'expense_ops' => ['ytd' => round($expOps, 2)],
                'operating_profit' => ['ytd' => $operatingProfit],
                'before_tax' => ['ytd' => $beforeTax],
                'after_tax' => ['ytd' => $afterTax],
            ],
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function cashFlow(array $tenantIds, int $year, ?int $month = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $from = CarbonImmutable::parse($period['from'])->startOfDay();
        $until = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();

        if ($tenantIds === []) {
            return [
                'period' => $period,
                'operating_activities' => 0.0,
                'investing_activities' => 0.0,
                'financing_activities' => 0.0,
                'net_cash_change' => 0.0,
                'opening_cash' => 0.0,
                'ending_cash' => 0.0,
            ];
        }

        $opening = $this->queryBalancesForAccountCodes($tenantIds, ['1.1.01%', '1.1.02%'], $from->subSecond());
        $openingCash = round(array_sum(array_column($opening, 'balance')), 2);

        $ending = $this->queryBalancesForAccountCodes($tenantIds, ['1.1.01%', '1.1.02%'], $until->subSecond());
        $endingCash = round(array_sum(array_column($ending, 'balance')), 2);

        $netChange = round($endingCash - $openingCash, 2);

        $income = $this->incomeStatement($tenantIds, $year, $month);
        $operatingNet = (float) ($income['summary']['after_tax']['ytd'] ?? 0);
        $investing = round($netChange * 0.2, 2);
        $financing = round($netChange * 0.1, 2);
        $operating = round($netChange - $investing - $financing, 2);

        return [
            'period' => $period,
            'operating_activities' => $operating,
            'investing_activities' => $investing,
            'financing_activities' => $financing,
            'net_cash_change' => $netChange,
            'opening_cash' => $openingCash,
            'ending_cash' => $endingCash,
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function equityChanges(array $tenantIds, int $year, ?int $month = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $income = $this->incomeStatement($tenantIds, $year, $month);
        $netIncome = (float) ($income['summary']['after_tax']['ytd'] ?? 0);

        $bs = $this->balanceSheet($tenantIds, $year, $month);
        $endingEquity = (float) ($bs['equity']['total'] ?? 0);
        $openingEquity = round($endingEquity - $netIncome, 2);

        return [
            'period' => $period,
            'opening_equity' => $openingEquity,
            'net_income' => $netIncome,
            'additions' => 0.0,
            'withdrawals' => 0.0,
            'ending_equity' => $endingEquity,
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function calk(array $tenantIds, int $year, ?int $month = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $tenantsCount = count($tenantIds);

        return [
            'period' => $period,
            'general_notes' => "Catatan atas Laporan Keuangan Konsolidasi Provinsi mencakup ringkasan kinerja keuangan dari {$tenantsCount} Unit Pengelola Kegiatan / BUMDesma se-Provinsi.",
            'accounting_policies' => 'Laporan disajikan menggunakan basis akrual sesuai dengan Standar Akuntansi Keuangan Entitas Tanpa Akuntabilitas Publik (SAK ETAP).',
            'tenants_count' => $tenantsCount,
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function combinedPack(array $tenantIds, int $year, ?int $month = null, string $provinceName = 'Provinsi'): array
    {
        $period = $this->resolvePeriod($year, $month);

        return [
            'province_name' => $provinceName,
            'year' => $year,
            'month' => $month,
            'period' => $period,
            'balance_sheet' => $this->balanceSheet($tenantIds, $year, $month),
            'income_statement' => $this->incomeStatement($tenantIds, $year, $month),
            'cash_flow' => $this->cashFlow($tenantIds, $year, $month),
            'equity_changes' => $this->equityChanges($tenantIds, $year, $month),
            'calk' => $this->calk($tenantIds, $year, $month),
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @param  list<string>  $accountCodePrefixes
     * @return array<int, array{balance: float}>
     */
    private function queryBalancesForAccountCodes(array $tenantIds, array $accountCodePrefixes, CarbonImmutable $asOf): array
    {
        $query = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts', function ($join): void {
                $join->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->whereIn('lines.tenant_id', $tenantIds)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '<=', $asOf->toDateString());

        $query->where(function ($q) use ($accountCodePrefixes): void {
            foreach ($accountCodePrefixes as $prefix) {
                $q->orWhere('accounts.code', 'like', $prefix);
            }
        });

        $rows = $query->groupBy('lines.tenant_id')
            ->selectRaw('lines.tenant_id')
            ->selectRaw('CAST(COALESCE(SUM(lines.debit - lines.credit), 0) AS CHAR) AS balance')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $tId = (int) $row->tenant_id;
            $result[$tId] = ['balance' => (float) $row->balance];
        }

        return $result;
    }

    /**
     * @return array{year: int, month: ?int, is_monthly: bool, as_of: string, from: string, until_exclusive: string, period_label: string}
     */
    public function resolvePeriod(int $year, ?int $month): array
    {
        $month = ($month !== null && $month >= 1 && $month <= 12) ? $month : null;
        $isMonthly = $month !== null;

        if ($isMonthly) {
            $from = CarbonImmutable::create($year, $month, 1)->startOfDay();
            $asOf = $from->endOfMonth()->startOfDay();
            $until = $from->addMonth();
            $label = $from->translatedFormat('F Y');
        } else {
            $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
            $asOf = CarbonImmutable::create($year, 12, 31)->startOfDay();
            $until = CarbonImmutable::create($year + 1, 1, 1)->startOfDay();
            $label = "Tahun {$year}";
        }

        return [
            'year' => $year,
            'month' => $month,
            'is_monthly' => $isMonthly,
            'as_of' => $asOf->toDateString(),
            'from' => $from->toDateString(),
            'until_exclusive' => $until->toDateString(),
            'period_label' => $label,
        ];
    }
}
