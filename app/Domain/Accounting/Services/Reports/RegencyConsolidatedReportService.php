<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Services\RegencyGeoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RegencyConsolidatedReportService
{
    /**
     * @return Collection<int, Tenant>
     */
    public function listKecamatans(DatabaseShard $shard, ?string $regencyCode = null): Collection
    {
        $query = Tenant::query()
            ->whereIn('status', ['active', 'read_only'])
            ->whereHas('placement', fn ($q) => $q->where('shard_id', $shard->row_id));

        if ($regencyCode !== null && $regencyCode !== '') {
            $query->where('regency_code', $regencyCode);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function dashboardMetrics(DatabaseShard $shard, array $tenantIds, int $year, ?int $month = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month ?? (int) date('n'), 1)->endOfMonth()->toDateString();
        $kecamatans = $this->listKecamatans($shard)->whereIn('row_id', $tenantIds)->values();

        if ($tenantIds === []) {
            return [
                'period' => $period,
                'summary' => [
                    'total_kecamatans' => 0,
                    'total_turnover' => 0.0,
                    'total_assets' => 0.0,
                    'avg_npl_ratio' => 0.0,
                    'npl_status' => 'Sehat',
                    'npl_tone' => 'success',
                    'total_cash' => 0.0,
                    'active_loans_count' => 0,
                    'active_loan_principal' => 0.0,
                    'total_tunggakan_pokok' => 0.0,
                    'net_income_ytd' => 0.0,
                    'revenue_ops_ytd' => 0.0,
                    'expense_ops_ytd' => 0.0,
                ],
                'kecamatans' => [],
            ];
        }

        // 1. Total Kas & Bank (Akun 1.1.01.xx + 1.1.02.xx)
        $cashBalances = $this->queryBalancesForAccountCodes($tenantIds, ['1.1.01%', '1.1.02%'], $asOf);
        $totalCash = round(array_sum(array_column($cashBalances, 'balance')), 2);

        // 2. Data Pinjaman Aktif, Angsuran, & Kolektibilitas NPL
        $loans = DB::connection('tenant')
            ->table('loans')
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', ['active', 'disbursed'])
            ->get(['row_id', 'tenant_id', 'principal_amount', 'disbursed_at']);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->whereIn('tenant_id', $tenantIds)
                ->whereIn('loan_row_id', $loanRowIds)
                ->where('due_date', '<=', $endOfMonth)
                ->groupBy(['tenant_id', 'loan_row_id'])
                ->selectRaw('tenant_id, loan_row_id, CAST(COALESCE(SUM(principal_due), 0) AS CHAR) as total_principal_due')
                ->get()
                ->groupBy('tenant_id');

        $allocations = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payment_allocations as a')
                ->join('loan_payments as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'a.tenant_id')
                        ->on('p.row_id', '=', 'a.payment_row_id');
                })
                ->whereIn('a.tenant_id', $tenantIds)
                ->whereIn('p.loan_row_id', $loanRowIds)
                ->where('a.component', 'principal')
                ->where('p.paid_at', '<=', $endOfMonth)
                ->groupBy(['a.tenant_id', 'p.loan_row_id'])
                ->selectRaw('a.tenant_id, p.loan_row_id, CAST(COALESCE(SUM(a.amount), 0) AS CHAR) as total_principal_paid')
                ->get()
                ->groupBy('tenant_id');

        // 3. Neraca & Akumulasi Aset Gabungan
        $balanceSheet = $this->balanceSheet($shard, $tenantIds, $year, $month);
        $totalAssets = (float) ($balanceSheet['summary']['total_assets'] ?? 0.0);
        $assetGroup = collect($balanceSheet['groups'] ?? [])->firstWhere('type', 'asset');
        $tenantAssetsMap = is_array($assetGroup) ? ($assetGroup['tenants'] ?? []) : [];

        // 4. Ringkasan Laba Rugi Konsolidasi YTD
        $incomeStatement = $this->incomeStatement($shard, $tenantIds, $year, $month);
        $totalNetIncomeYtd = (float) ($incomeStatement['summary']['after_tax']['ytd'] ?? 0);
        $totalRevenueOpsYtd = (float) ($incomeStatement['summary']['revenue_ops']['ytd'] ?? 0);
        $totalExpenseOpsYtd = (float) ($incomeStatement['summary']['expense_ops']['ytd'] ?? 0);

        // 5. Rekap Per Kecamatan & Perhitungan Metrik Supervisi
        $recap = [];
        $regencyCode = $shard->regency_code ?: '3301';
        $totalTurnover = 0.0;
        $totalActivePrincipal = 0.0;
        $totalTunggakanPokok = 0.0;
        $totalActiveLoans = 0;
        $totalKecCount = count($kecamatans);

        foreach ($kecamatans as $index => $kecamatan) {
            $tId = (int) $kecamatan->row_id;
            $kecCash = round((float) ($cashBalances[$tId]['balance'] ?? 0), 2);
            $kecAssets = round((float) ($tenantAssetsMap[$tId] ?? 0.0), 2);

            $tLoans = $loans->where('tenant_id', $tId);
            $tInsts = ($installments->get($tId) ?? collect())->keyBy('loan_row_id');
            $tAllocs = ($allocations->get($tId) ?? collect())->keyBy('loan_row_id');

            $tAlokasi = 0.0;
            $tSaldo = 0.0;
            $tTunggakan = 0.0;

            foreach ($tLoans as $l) {
                $lId = (int) $l->row_id;
                $alokasi = (float) $l->principal_amount;
                $tAlokasi += $alokasi;

                $due = (float) ($tInsts->get($lId)->total_principal_due ?? 0.0);
                $paid = (float) ($tAllocs->get($lId)->total_principal_paid ?? 0.0);

                $saldo = max(0.0, round($alokasi - $paid, 2));
                $tunggakan = max(0.0, round($due - $paid, 2));

                $tSaldo += $saldo;
                $tTunggakan += $tunggakan;
            }

            $totalTurnover += $tAlokasi;
            $totalActivePrincipal += $tSaldo;
            $totalTunggakanPokok += $tTunggakan;
            $totalActiveLoans += $tLoans->count();

            $kecNplRatio = $tSaldo > 0 ? round(($tTunggakan / $tSaldo) * 100, 2) : 0.0;
            $nplEval = self::evaluateNpl($kecNplRatio);

            $savedGeo = RegencyGeoService::resolveSavedCoordinate($kecamatan->map_latitude, $kecamatan->map_longitude, $kecamatan->map_zoom);
            $geo = $savedGeo ?? RegencyGeoService::resolveDistrictCoordinate(
                $kecamatan->district_code,
                $kecamatan->regency_code ?: $regencyCode,
                $index,
                $totalKecCount,
            );

            $kecGroups = DB::connection('tenant')
                ->table('groups')
                ->where('tenant_id', $tId)
                ->where('status', 'active')
                ->count();

            $kecMembers = DB::connection('tenant')
                ->table('members')
                ->where('tenant_id', $tId)
                ->where('status', 'active')
                ->count();

            $recap[] = [
                'tenant_id' => $tId,
                'code' => $kecamatan->code,
                'name' => $kecamatan->name,
                'district_code' => $kecamatan->district_code,
                'cash' => $kecCash,
                'total_assets' => $kecAssets,
                'turnover' => round($tAlokasi, 2),
                'active_loans' => $tLoans->count(),
                'active_principal' => round($tSaldo, 2),
                'tunggakan_pokok' => round($tTunggakan, 2),
                'npl_ratio' => $kecNplRatio,
                'npl_status' => $nplEval['status'],
                'npl_tone' => $nplEval['tone'],
                'groups_count' => $kecGroups,
                'members_count' => $kecMembers,
                'lat' => $geo['lat'],
                'lng' => $geo['lng'],
                'zoom' => $geo['zoom'] ?? 13,
            ];
        }

        $avgNplRatio = $totalActivePrincipal > 0
            ? round(($totalTunggakanPokok / $totalActivePrincipal) * 100, 2)
            : 0.0;
        $regencyNplEval = self::evaluateNpl($avgNplRatio);

        return [
            'period' => $period,
            'summary' => [
                'total_kecamatans' => count($tenantIds),
                'total_turnover' => round($totalTurnover, 2),
                'total_assets' => round($totalAssets, 2),
                'avg_npl_ratio' => $avgNplRatio,
                'npl_status' => $regencyNplEval['status'],
                'npl_tone' => $regencyNplEval['tone'],
                'total_cash' => $totalCash,
                'active_loans_count' => $totalActiveLoans,
                'active_loan_principal' => round($totalActivePrincipal, 2),
                'total_tunggakan_pokok' => round($totalTunggakanPokok, 2),
                'net_income_ytd' => $totalNetIncomeYtd,
                'revenue_ops_ytd' => $totalRevenueOpsYtd,
                'expense_ops_ytd' => $totalExpenseOpsYtd,
            ],
            'kecamatans' => $recap,
        ];
    }

    /**
     * Evaluasi predikat kesehatan rasio NPL berdasarkan standar BUMDesma / LKD.
     *
     * @return array{status: string, tone: string}
     */
    public static function evaluateNpl(float $ratio): array
    {
        if ($ratio <= 5.0) {
            return ['status' => 'Sehat (≤ 5%)', 'tone' => 'success'];
        }
        if ($ratio <= 10.0) {
            return ['status' => 'Cukup Sehat (5–10%)', 'tone' => 'primary'];
        }
        if ($ratio <= 25.0) {
            return ['status' => 'Kurang Sehat (10–25%)', 'tone' => 'warning'];
        }

        return ['status' => 'Tidak Sehat (> 25%)', 'tone' => 'error'];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function balanceSheet(DatabaseShard $shard, array $tenantIds, int $year, ?int $month = null, ?int $specificTenantId = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $targetTenantIds = $specificTenantId !== null ? [$specificTenantId] : $tenantIds;

        $kecamatans = $this->listKecamatans($shard)->whereIn('row_id', $tenantIds)->values();

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance', 'level', 'parent_row_id', 'is_postable'])
            ->unique('code')
            ->values();

        // Movements & Openings per tenant (keyed by [tenant_id][account_code])
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $untilExclusive = $asOf->addDay()->startOfDay();

        $movements = $this->queryMovementsByCode($targetTenantIds, $yearStart, $untilExclusive);
        $openings = $this->queryOpeningsByCode($targetTenantIds, $year);

        // Net income per tenant for current earnings (3.2.02.01)
        $netIncomePerTenant = $this->computeNetIncomePerTenant($targetTenantIds, $year, $month);

        $breakdownByAccountCode = [];
        foreach ($accounts as $account) {
            $code = (string) $account->code;
            $isCurrentEarnings = ($code === AccountBalanceQuery::CURRENT_EARNINGS_CODE);

            $perTenantBalances = [];
            $totalSigned = 0.0;

            foreach ($targetTenantIds as $tId) {
                if ($isCurrentEarnings) {
                    $bal = (float) ($netIncomePerTenant[$tId] ?? 0.0);
                } else {
                    $openPair = $openings[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];
                    $movPair = $movements[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];

                    $totDeb = $openPair['debit'] + $movPair['debit'];
                    $totCred = $openPair['credit'] + $movPair['credit'];

                    $bal = $this->calcSignedBalance($account, $totDeb, $totCred);
                }

                $perTenantBalances[$tId] = round($bal, 2);
                $totalSigned += $bal;
            }

            $breakdownByAccountCode[$code] = [
                'account' => $account,
                'total' => round($totalSigned, 2),
                'tenants' => $perTenantBalances,
            ];
        }

        // Build Level 1 / Level 2 / Level 3 hierarchy
        $groups = [];
        $level1 = $accounts->where('level', 1);

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($level1 as $l1) {
            $l1Total = 0.0;
            $l1PerTenant = array_fill_keys($targetTenantIds, 0.0);
            $l2Groups = [];

            $l2Accounts = $accounts->filter(fn ($a) => (int) $a->level === 2 && str_starts_with((string) $a->code, substr((string) $l1->code, 0, 1).'.'));
            foreach ($l2Accounts as $l2) {
                $l2Total = 0.0;
                $l2PerTenant = array_fill_keys($targetTenantIds, 0.0);
                $l3Rows = [];

                $childAccounts = $accounts->filter(fn ($a) => $this->isDescendantOf((string) $a->code, (string) $l2->code) && $a->is_postable);
                foreach ($childAccounts as $child) {
                    $code = (string) $child->code;
                    $childData = $breakdownByAccountCode[$code] ?? [
                        'account' => $child,
                        'total' => 0.0,
                        'tenants' => array_fill_keys($targetTenantIds, 0.0),
                    ];

                    $l3Rows[] = [
                        'row_id' => (int) $child->row_id,
                        'code' => $code,
                        'name' => (string) $child->name,
                        'total' => $childData['total'],
                        'tenants' => $childData['tenants'],
                    ];

                    $l2Total += $childData['total'];
                    foreach ($targetTenantIds as $tId) {
                        $l2PerTenant[$tId] += $childData['tenants'][$tId] ?? 0.0;
                    }
                }

                $l2Groups[] = [
                    'code' => (string) $l2->code,
                    'name' => (string) $l2->name,
                    'total' => round($l2Total, 2),
                    'tenants' => array_map(fn ($v) => round($v, 2), $l2PerTenant),
                    'rows' => $l3Rows,
                ];

                $l1Total += $l2Total;
                foreach ($targetTenantIds as $tId) {
                    $l1PerTenant[$tId] += $l2PerTenant[$tId];
                }
            }

            $groups[] = [
                'type' => (string) $l1->account_type,
                'code' => (string) $l1->code,
                'name' => (string) $l1->name,
                'total' => round($l1Total, 2),
                'tenants' => array_map(fn ($v) => round($v, 2), $l1PerTenant),
                'subgroups' => $l2Groups,
            ];

            if ($l1->account_type === 'asset') {
                $totalAssets += $l1Total;
            } elseif ($l1->account_type === 'liability') {
                $totalLiabilities += $l1Total;
            } elseif ($l1->account_type === 'equity') {
                $totalEquity += $l1Total;
            }
        }

        return [
            'period' => $period,
            'is_consolidated' => $specificTenantId === null,
            'specific_tenant_id' => $specificTenantId,
            'kecamatans' => $kecamatans->map(fn ($k) => [
                'id' => (int) $k->row_id,
                'name' => $k->name,
                'code' => $k->code,
                'district_code' => $k->district_code,
            ])->all(),
            'groups' => $groups,
            'summary' => [
                'total_assets' => round($totalAssets, 2),
                'total_liabilities' => round($totalLiabilities, 2),
                'total_equity' => round($totalEquity, 2),
                'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
                'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.05,
            ],
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function incomeStatement(DatabaseShard $shard, array $tenantIds, int $year, ?int $month = null, ?int $specificTenantId = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $periodFrom = CarbonImmutable::parse($period['from'])->startOfDay();
        $periodUntil = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();
        $priorUntil = $period['is_monthly'] ? $periodFrom : $yearStart;

        $targetTenantIds = $specificTenantId !== null ? [$specificTenantId] : $tenantIds;
        $kecamatans = $this->listKecamatans($shard)->whereIn('row_id', $tenantIds)->values();

        $openings = $this->queryOpeningsByCode($targetTenantIds, $year);
        $ytdMovements = $this->queryMovementsByCode($targetTenantIds, $yearStart, $asOf->addDay()->startOfDay());
        $priorMovements = $period['is_monthly'] ? $this->queryMovementsByCode($targetTenantIds, $yearStart, $priorUntil) : [];
        $periodMovements = $this->queryMovementsByCode($targetTenantIds, $periodFrom, $periodUntil);

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance', 'level', 'parent_row_id'])
            ->unique('code')
            ->values();

        $level2Parents = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('level', 2)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'level'])
            ->unique('code')
            ->values();

        $groups = [];
        foreach ($level2Parents as $parent) {
            $children = [];
            $sumPrior = 0.0;
            $sumCurrent = 0.0;
            $sumYtd = 0.0;

            foreach ($accounts as $account) {
                if (! $this->isUnderLevel2((string) $account->code, (string) $parent->code)) {
                    continue;
                }

                $code = (string) $account->code;
                $rowPrior = 0.0;
                $rowCurrent = 0.0;
                $rowYtd = 0.0;
                $perTenantYtd = [];

                foreach ($targetTenantIds as $tId) {
                    $openPair = $openings[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];
                    $ytdPair = $ytdMovements[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];
                    $priorPair = $priorMovements[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];
                    $curPair = $periodMovements[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];

                    $tYtd = $this->calcSignedBalance($account, $openPair['debit'] + $ytdPair['debit'], $openPair['credit'] + $ytdPair['credit']);
                    $tPrior = $period['is_monthly']
                        ? $this->calcSignedBalance($account, $openPair['debit'] + $priorPair['debit'], $openPair['credit'] + $priorPair['credit'])
                        : 0.0;
                    $tCurrent = $this->calcSignedBalance($account, $curPair['debit'], $curPair['credit']);

                    $rowPrior += $tPrior;
                    $rowCurrent += $tCurrent;
                    $rowYtd += $tYtd;
                    $perTenantYtd[$tId] = round($tYtd, 2);
                }

                $children[] = [
                    'row_id' => (int) $account->row_id,
                    'code' => $code,
                    'name' => (string) $account->name,
                    'prior' => round($rowPrior, 2),
                    'current' => round($rowCurrent, 2),
                    'ytd' => round($rowYtd, 2),
                    'tenants' => $perTenantYtd,
                ];

                $sumPrior += $rowPrior;
                $sumCurrent += $rowCurrent;
                $sumYtd += $rowYtd;
            }

            if ($children === []) {
                continue;
            }

            $groups[] = [
                'code' => (string) $parent->code,
                'name' => (string) $parent->name,
                'account_type' => (string) $parent->account_type,
                'bucket' => $this->bucket((string) $parent->code, (string) $parent->account_type),
                'children' => $children,
                'prior' => round($sumPrior, 2),
                'current' => round($sumCurrent, 2),
                'ytd' => round($sumYtd, 2),
            ];
        }

        $sumBucket = fn (string $bucket, string $field): float => round(
            array_sum(array_map(
                fn (array $g) => $g['bucket'] === $bucket ? $g[$field] : 0.0,
                $groups,
            )),
            2,
        );

        $revOpsYtd = $sumBucket('revenue_ops', 'ytd');
        $expOpsYtd = $sumBucket('expense_ops', 'ytd');
        $revNonYtd = $sumBucket('revenue_non', 'ytd');
        $expNonYtd = $sumBucket('expense_non', 'ytd');
        $taxYtd = $sumBucket('tax', 'ytd');

        $aYtd = round($revOpsYtd - $expOpsYtd, 2);
        $bYtd = round($revNonYtd - $expNonYtd, 2);
        $beforeTax = round($aYtd + $bYtd, 2);
        $afterTax = round($beforeTax - $taxYtd, 2);

        $revOpsCur = $sumBucket('revenue_ops', 'current');
        $expOpsCur = $sumBucket('expense_ops', 'current');
        $revNonCur = $sumBucket('revenue_non', 'current');
        $expNonCur = $sumBucket('expense_non', 'current');
        $taxCur = $sumBucket('tax', 'current');
        $aCur = round($revOpsCur - $expOpsCur, 2);
        $bCur = round($revNonCur - $expNonCur, 2);
        $beforeTaxCur = round($aCur + $bCur, 2);
        $afterTaxCur = round($beforeTaxCur - $taxCur, 2);

        $revOpsPrior = $sumBucket('revenue_ops', 'prior');
        $expOpsPrior = $sumBucket('expense_ops', 'prior');
        $revNonPrior = $sumBucket('revenue_non', 'prior');
        $expNonPrior = $sumBucket('expense_non', 'prior');
        $taxPrior = $sumBucket('tax', 'prior');
        $aPrior = round($revOpsPrior - $expOpsPrior, 2);
        $bPrior = round($revNonPrior - $expNonPrior, 2);
        $beforeTaxPrior = round($aPrior + $bPrior, 2);
        $afterTaxPrior = round($beforeTaxPrior - $taxPrior, 2);

        return [
            'period' => $period,
            'is_consolidated' => $specificTenantId === null,
            'specific_tenant_id' => $specificTenantId,
            'kecamatans' => $kecamatans->map(fn ($k) => [
                'id' => (int) $k->row_id,
                'name' => $k->name,
                'code' => $k->code,
                'district_code' => $k->district_code,
            ])->all(),
            'header_lalu' => $period['is_monthly'] ? 'Bulan Lalu' : 'Tahun Lalu',
            'header_sekarang' => $period['is_monthly'] ? 'Bulan Ini' : 'Tahun Ini',
            'groups' => $groups,
            'summary' => [
                'revenue_ops' => ['prior' => $revOpsPrior, 'current' => $revOpsCur, 'ytd' => $revOpsYtd],
                'expense_ops' => ['prior' => $expOpsPrior, 'current' => $expOpsCur, 'ytd' => $expOpsYtd],
                'operating' => ['prior' => $aPrior, 'current' => $aCur, 'ytd' => $aYtd],
                'non_operating' => ['prior' => $bPrior, 'current' => $bCur, 'ytd' => $bYtd],
                'before_tax' => ['prior' => $beforeTaxPrior, 'current' => $beforeTaxCur, 'ytd' => $beforeTax],
                'tax' => ['prior' => $taxPrior, 'current' => $taxCur, 'ytd' => $taxYtd],
                'after_tax' => ['prior' => $afterTaxPrior, 'current' => $afterTaxCur, 'ytd' => $afterTax],
            ],
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function generalLedger(DatabaseShard $shard, array $tenantIds, int $year, ?int $month, int|string $accountIdOrCode, ?int $specificTenantId = null, ?string $day = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $targetTenantIds = $specificTenantId !== null ? [$specificTenantId] : $tenantIds;
        $kecamatans = $this->listKecamatans($shard)->keyBy('row_id');

        $account = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_postable', true)
            ->where(function ($q) use ($accountIdOrCode): void {
                if (is_numeric($accountIdOrCode)) {
                    $q->where('row_id', (int) $accountIdOrCode)->orWhere('code', (string) $accountIdOrCode);
                } else {
                    $q->where('code', (string) $accountIdOrCode);
                }
            })
            ->first(['row_id', 'code', 'name', 'account_type', 'normal_balance']);

        if ($account === null) {
            throw new InvalidArgumentException('Akun tidak ditemukan atau tidak postable.');
        }

        $accountCode = (string) $account->code;
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $periodFrom = CarbonImmutable::parse($period['from'])->startOfDay();
        $periodUntil = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();

        if (is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
            $periodFrom = CarbonImmutable::parse($day)->startOfDay();
            $periodUntil = $periodFrom->addDay();
            $period['period_label'] = $periodFrom->format('d/m/Y');
            $period['as_of'] = $periodFrom->toDateString();
            $period['from'] = $periodFrom->toDateString();
            $period['until_exclusive'] = $periodUntil->toDateString();
            $period['is_monthly'] = true;
            $month = (int) $periodFrom->month;
        }

        $openings = $this->queryOpeningsByCode($targetTenantIds, $year);
        $priorMovements = $this->queryMovementsByCode($targetTenantIds, $yearStart, $periodFrom);

        $openingTotal = 0.0;
        foreach ($targetTenantIds as $tId) {
            $openPair = $openings[$tId][$accountCode] ?? ['debit' => 0.0, 'credit' => 0.0];
            $priorPair = $priorMovements[$tId][$accountCode] ?? ['debit' => 0.0, 'credit' => 0.0];

            $openingTotal += $this->calcSignedBalance(
                $account,
                $openPair['debit'] + $priorPair['debit'],
                $openPair['credit'] + $priorPair['credit'],
            );
        }

        // Fetch transaction entries across target tenants joining accounts by code
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
            ->whereIn('lines.tenant_id', $targetTenantIds)
            ->where('accounts.code', $accountCode)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $periodFrom->toDateString())
            ->where('entries.transaction_date', '<', $periodUntil->toDateString())
            ->orderBy('entries.transaction_date')
            ->orderBy('entries.journal_number')
            ->orderBy('lines.row_id')
            ->select([
                'entries.tenant_id',
                'entries.transaction_date',
                'entries.journal_number',
                'entries.description as entry_description',
                'lines.description as line_description',
                'lines.debit',
                'lines.credit',
            ])
            ->get();

        $running = $openingTotal;
        $entries = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $deb = (float) $line->debit;
            $cred = (float) $line->credit;
            $normal = strtoupper((string) ($account->normal_balance ?? 'D'));
            $isDebitNormal = ($normal === 'D' || $normal === 'DEBIT' || in_array($account->account_type, ['asset', 'expense'], true));
            $change = $isDebitNormal ? ($deb - $cred) : ($cred - $deb);
            $running += $change;

            $totalDebit += $deb;
            $totalCredit += $cred;

            $tId = (int) $line->tenant_id;
            $tenantObj = $kecamatans->get($tId);

            $entries[] = [
                'tenant_id' => $tId,
                'kecamatan_name' => $tenantObj?->name ?? 'Kecamatan #'.$tId,
                'date' => (string) $line->transaction_date,
                'voucher_number' => (string) ($line->journal_number ?? ''), 'journal_number' => (string) ($line->journal_number ?? ''),
                'description' => (string) ($line->line_description ?: $line->entry_description),
                'debit' => round($deb, 2),
                'credit' => round($cred, 2),
                'balance' => round($running, 2),
            ];
        }

        $allAccounts = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name'])
            ->unique('code')
            ->values();

        return [
            'period' => $period,
            'is_consolidated' => $specificTenantId === null,
            'specific_tenant_id' => $specificTenantId,
            'account' => [
                'row_id' => (int) $account->row_id,
                'code' => $accountCode,
                'name' => (string) $account->name,
                'normal_balance' => (string) $account->normal_balance,
            ],
            'opening_balance' => round($openingTotal, 2),
            'closing_balance' => round($running, 2),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'entries' => $entries,
            'accounts' => $allAccounts->map(fn ($a) => [
                'row_id' => (int) $a->row_id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
            ])->all(),
            'kecamatans' => $kecamatans->values()->map(fn ($k) => [
                'id' => (int) $k->row_id,
                'name' => $k->name,
                'code' => $k->code,
            ])->all(),
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function cashFlow(DatabaseShard $shard, array $tenantIds, int $year, ?int $month = null, ?int $specificTenantId = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $periodFrom = CarbonImmutable::parse($period['from'])->startOfDay();
        $periodUntil = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();
        $targetTenantIds = $specificTenantId !== null ? [$specificTenantId] : $tenantIds;
        $kecamatans = $this->listKecamatans($shard)->whereIn('row_id', $tenantIds)->values();

        // 1. Kas Awal (Opening + Prior movements)
        $cashBalances = $this->queryBalancesForAccountCodes($targetTenantIds, ['1.1.01%', '1.1.02%'], $periodFrom);
        $cashOpeningTotal = round(array_sum(array_column($cashBalances, 'balance')), 2);

        // 2. Direct Cash Flow by cash counterpart lines
        $cashRows = DB::connection('tenant')
            ->table('journal_lines as cash_lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'cash_lines.tenant_id')
                    ->on('entries.row_id', '=', 'cash_lines.journal_entry_row_id');
            })
            ->join('accounts as cash_acc', function ($join): void {
                $join->on('cash_acc.tenant_id', '=', 'cash_lines.tenant_id')
                    ->on('cash_acc.row_id', '=', 'cash_lines.account_row_id');
            })
            ->join('journal_lines as other_lines', function ($join): void {
                $join->on('other_lines.tenant_id', '=', 'cash_lines.tenant_id')
                    ->on('other_lines.journal_entry_row_id', '=', 'cash_lines.journal_entry_row_id')
                    ->on('other_lines.row_id', '!=', 'cash_lines.row_id');
            })
            ->join('accounts as counter_acc', function ($join): void {
                $join->on('counter_acc.tenant_id', '=', 'other_lines.tenant_id')
                    ->on('counter_acc.row_id', '=', 'other_lines.account_row_id');
            })
            ->whereIn('cash_lines.tenant_id', $targetTenantIds)
            ->where(function ($q): void {
                $q->where('cash_acc.code', 'like', '1.1.01%')->orWhere('cash_acc.code', 'like', '1.1.02%');
            })
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $periodFrom->toDateString())
            ->where('entries.transaction_date', '<', $periodUntil->toDateString())
            ->select([
                'counter_acc.code as account_code',
                'counter_acc.name as account_name',
                'counter_acc.account_type',
                DB::raw('CAST(SUM(cash_lines.debit) AS CHAR) as cash_in'),
                DB::raw('CAST(SUM(cash_lines.credit) AS CHAR) as cash_out'),
            ])
            ->groupBy(['counter_acc.code', 'counter_acc.name', 'counter_acc.account_type'])
            ->get();

        $operatingIn = 0.0;
        $operatingOut = 0.0;
        $investingIn = 0.0;
        $investingOut = 0.0;
        $financingIn = 0.0;
        $financingOut = 0.0;

        $operatingItems = [];
        $investingItems = [];
        $financingItems = [];

        foreach ($cashRows as $row) {
            $code = (string) $row->account_code;
            $in = (float) $row->cash_in;
            $out = (float) $row->cash_out;

            $item = [
                'code' => $code,
                'name' => (string) $row->account_name,
                'cash_in' => round($in, 2),
                'cash_out' => round($out, 2),
                'net' => round($in - $out, 2),
            ];

            if (str_starts_with($code, '4.') || str_starts_with($code, '5.')) {
                $operatingItems[] = $item;
                $operatingIn += $in;
                $operatingOut += $out;
            } elseif (str_starts_with($code, '1.2') || str_starts_with($code, '1.3')) {
                $investingItems[] = $item;
                $investingIn += $in;
                $investingOut += $out;
            } else {
                $financingItems[] = $item;
                $financingIn += $in;
                $financingOut += $out;
            }
        }

        $netOperating = round($operatingIn - $operatingOut, 2);
        $netInvesting = round($investingIn - $investingOut, 2);
        $netFinancing = round($financingIn - $financingOut, 2);
        $netCashChange = round($netOperating + $netInvesting + $netFinancing, 2);
        $cashClosingTotal = round($cashOpeningTotal + $netCashChange, 2);

        return [
            'period' => $period,
            'is_consolidated' => $specificTenantId === null,
            'specific_tenant_id' => $specificTenantId,
            'kecamatans' => $kecamatans->map(fn ($k) => [
                'id' => (int) $k->row_id,
                'name' => $k->name,
                'code' => $k->code,
                'district_code' => $k->district_code,
            ])->all(),
            'sections' => [
                'operating' => [
                    'title' => 'Arus Kas dari Aktivitas Operasi',
                    'items' => $operatingItems,
                    'total_in' => round($operatingIn, 2),
                    'total_out' => round($operatingOut, 2),
                    'net' => $netOperating,
                ],
                'investing' => [
                    'title' => 'Arus Kas dari Aktivitas Investasi',
                    'items' => $investingItems,
                    'total_in' => round($investingIn, 2),
                    'total_out' => round($investingOut, 2),
                    'net' => $netInvesting,
                ],
                'financing' => [
                    'title' => 'Arus Kas dari Aktivitas Pendanaan',
                    'items' => $financingItems,
                    'total_in' => round($financingIn, 2),
                    'total_out' => round($financingOut, 2),
                    'net' => $netFinancing,
                ],
            ],
            'reconciliation' => [
                'cash_opening' => round($cashOpeningTotal, 2),
                'net_change' => $netCashChange,
                'cash_closing' => $cashClosingTotal,
            ],
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<string, mixed>
     */
    public function calk(DatabaseShard $shard, array $tenantIds, int $year, ?int $month = null, ?int $specificTenantId = null): array
    {
        $period = $this->resolvePeriod($year, $month);
        $bs = $this->balanceSheet($shard, $tenantIds, $year, $month, $specificTenantId);
        $is = $this->incomeStatement($shard, $tenantIds, $year, $month, $specificTenantId);
        $cf = $this->cashFlow($shard, $tenantIds, $year, $month, $specificTenantId);
        $dashboard = $this->dashboardMetrics($shard, $tenantIds, $year, $month);

        $highlights = [
            [
                'key' => 'net_income',
                'label' => 'Laba (Rugi) Bersih Gabungan YTD',
                'value' => $is['summary']['after_tax']['ytd'] ?? 0,
                'tone' => ((float) ($is['summary']['after_tax']['ytd'] ?? 0)) >= 0 ? 'success' : 'danger',
            ],
            [
                'key' => 'cash_closing',
                'label' => 'Total Kas & Setara Kas Gabungan',
                'value' => $cf['reconciliation']['cash_closing'] ?? 0,
                'tone' => 'primary',
            ],
            [
                'key' => 'active_principal',
                'label' => 'Total Portofolio Pinjaman Bergulir',
                'value' => $dashboard['summary']['active_loan_principal'] ?? 0,
                'tone' => 'info',
            ],
            [
                'key' => 'total_assets',
                'label' => 'Total Aset Gabungan',
                'value' => $bs['summary']['total_assets'] ?? 0,
                'tone' => 'neutral',
            ],
        ];

        return [
            'period' => $period,
            'is_consolidated' => $specificTenantId === null,
            'specific_tenant_id' => $specificTenantId,
            'highlights' => $highlights,
            'kecamatans' => $dashboard['kecamatans'],
            'balance_sheet' => $bs,
            'income_statement' => $is,
            'cash_flow' => $cf,
        ];
    }

    // ────────────────────────── Helpers & Queries ──────────────────────────

    /**
     * @param  list<int>  $tenantIds
     * @param  list<string>  $patterns
     * @return array<int, array{balance: float}>
     */
    private function queryBalancesForAccountCodes(array $tenantIds, array $patterns, CarbonImmutable $asOf): array
    {
        $year = (int) $asOf->year;
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $untilExclusive = $asOf->addDay()->startOfDay();

        $openings = $this->queryOpeningsByCode($tenantIds, $year);
        $movements = $this->queryMovementsByCode($tenantIds, $yearStart, $untilExclusive);

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->where(function ($q) use ($patterns): void {
                foreach ($patterns as $pattern) {
                    $q->orWhere('code', 'like', $pattern);
                }
            })
            ->where('is_postable', true)
            ->get(['row_id', 'code', 'account_type', 'normal_balance']);

        $result = [];
        foreach ($tenantIds as $tId) {
            $sum = 0.0;
            foreach ($accounts as $acc) {
                $code = (string) $acc->code;
                $openPair = $openings[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];
                $movPair = $movements[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];

                $sum += $this->calcSignedBalance(
                    $acc,
                    $openPair['debit'] + $movPair['debit'],
                    $openPair['credit'] + $movPair['credit'],
                );
            }
            $result[$tId] = ['balance' => $sum];
        }

        return $result;
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<int, float>
     */
    private function computeNetIncomePerTenant(array $tenantIds, int $year, ?int $month): array
    {
        $period = $this->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $untilExclusive = $asOf->addDay()->startOfDay();

        $openings = $this->queryOpeningsByCode($tenantIds, $year);
        $movements = $this->queryMovementsByCode($tenantIds, $yearStart, $untilExclusive);

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->get(['row_id', 'code', 'account_type', 'normal_balance'])
            ->unique('code');

        $netIncomes = array_fill_keys($tenantIds, 0.0);

        foreach ($accounts as $account) {
            $code = (string) $account->code;
            foreach ($tenantIds as $tId) {
                $openPair = $openings[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];
                $movPair = $movements[$tId][$code] ?? ['debit' => 0.0, 'credit' => 0.0];

                $signed = $this->calcSignedBalance(
                    $account,
                    $openPair['debit'] + $movPair['debit'],
                    $openPair['credit'] + $movPair['credit'],
                );

                if ($account->account_type === 'revenue') {
                    $netIncomes[$tId] += $signed;
                } else {
                    $netIncomes[$tId] -= $signed;
                }
            }
        }

        return array_map(fn ($v) => round($v, 2), $netIncomes);
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<int, array<string, array{debit: float, credit: float}>>
     */
    private function queryMovementsByCode(array $tenantIds, CarbonImmutable $from, CarbonImmutable $untilExclusive): array
    {
        $rows = DB::connection('tenant')
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
            ->where('entries.transaction_date', '<', $untilExclusive->toDateString())
            ->groupBy(['lines.tenant_id', 'accounts.code'])
            ->selectRaw('lines.tenant_id')
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('CAST(COALESCE(SUM(lines.debit), 0) AS CHAR) AS debit')
            ->selectRaw('CAST(COALESCE(SUM(lines.credit), 0) AS CHAR) AS credit')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $tId = (int) $row->tenant_id;
            $code = (string) $row->account_code;
            $result[$tId][$code] = [
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<int, array<string, array{debit: float, credit: float}>>
     */
    private function queryOpeningsByCode(array $tenantIds, int $year): array
    {
        $rows = DB::connection('tenant')
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
            ->where('entries.source_type', 'opening_balance')
            ->where('entries.status', 'posted')
            ->whereYear('entries.transaction_date', $year)
            ->groupBy(['lines.tenant_id', 'accounts.code'])
            ->selectRaw('lines.tenant_id')
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('CAST(COALESCE(SUM(lines.debit), 0) AS CHAR) AS debit')
            ->selectRaw('CAST(COALESCE(SUM(lines.credit), 0) AS CHAR) AS credit')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $tId = (int) $row->tenant_id;
            $code = (string) $row->account_code;
            $result[$tId][$code] = [
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ];
        }

        return $result;
    }

    private function calcSignedBalance(Account $account, float $debit, float $credit): float
    {
        $normal = strtoupper((string) ($account->normal_balance ?? 'D'));
        $type = (string) ($account->account_type ?? '');

        if ($normal === 'C' || $normal === 'CREDIT' || in_array($type, ['liability', 'equity', 'revenue'], true)) {
            return round($credit - $debit, 2);
        }

        return round($debit - $credit, 2);
    }

    private function isDescendantOf(string $code, string $parentCode): bool
    {
        $parentPrefix = rtrim($parentCode, '0.');

        return str_starts_with($code, $parentPrefix) && $code !== $parentCode;
    }

    private function isUnderLevel2(string $code, string $parentCode): bool
    {
        $parts = explode('.', $parentCode);
        if (count($parts) < 2) {
            return str_starts_with($code, $parentCode);
        }
        $prefix = $parts[0].'.'.$parts[1].'.';

        return str_starts_with($code, $prefix) && $code !== $parentCode;
    }

    private function bucket(string $code, string $type): string
    {
        if (str_starts_with($code, '5.4')) {
            return 'tax';
        }
        if ($type === 'revenue') {
            return str_starts_with($code, '4.1') ? 'revenue_ops' : 'revenue_non';
        }

        if (str_starts_with($code, '5.1') || str_starts_with($code, '5.2')) {
            return 'expense_ops';
        }

        return 'expense_non';
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
