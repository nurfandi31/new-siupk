<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Lending\Services\Reports\CollectibilityReportService;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Penilaian Tingkat Kesehatan LKM (OJK).
 *
 * Mendukung dua kerangka laporan legacy:
 *  - POJK 19/POJK.05/2021 (6 rasio, skor 0-100)
 *  - POJK 41/POJK.05/2024 + POJK 49/POJK.05/2024 (komposit 0-100)
 *
 * Sumber data:
 *  - AllowanceService (kolektibilitas, CKP, PPAP)
 *  - OjkBalanceSheetService (aset, liabilitas, kas)
 *  - OjkIncomeStatementService (pendapatan, beban, surplus)
 *  - CollectibilityReportService (tunggakan pokok)
 */
final class OjkHealthService
{
    public function __construct(
        private readonly AllowanceService $allowance,
        private readonly OjkBalanceSheetService $balanceSheet,
        private readonly OjkIncomeStatementService $incomeStatement,
        private readonly CollectibilityReportService $collectibility,
        private readonly AccountBalanceQuery $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, int $month, string $framework = '41'): array
    {
        $framework = $framework === '19' ? '19' : '41';

        $allowance = $this->allowance->buildReport($year, $month, 'all');
        $balance = $this->balanceSheet->build($year, $month);
        $income = $this->incomeStatement->build($year, $month);
        $portfolio = $this->collectibility->buildDesa($year, $month, 'all', 'all');

        $asOf = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->startOfDay();

        $totalAssets = (float) ($balance['totals']['assets'] ?? 0);
        $totalLiabilities = (float) ($balance['totals']['liabilities'] ?? 0);
        $cash = (float) ($balance['kas_dan_setara_kas'] ?? 0);
        $currentLiabilities = (float) ($balance['liabilitas_lancar'] ?? 0);
        $equity = round($totalAssets - $totalLiabilities, 2);
        $paidInCapital = $this->paidInCapital($asOf, $equity);

        $revenue = 0.0;
        $expense = 0.0;
        foreach (($income['groups'] ?? []) as $group) {
            $value = (float) ($group['ytd'] ?? 0);
            if (($group['account_type'] ?? '') === 'revenue') {
                $revenue += $value;
            } elseif (($group['account_type'] ?? '') === 'expense') {
                $expense += $value;
            }
        }
        $revenue = round($revenue, 2);
        $expense = round($expense, 2);
        $surplus = round($revenue - $expense, 2);

        $outstanding = (float) ($allowance['totals']['outstanding'] ?? 0);
        $ckp = (float) ($allowance['totals']['allowance_formed'] ?? 0);
        $sumKolek = (float) ($allowance['totals']['allowance_required'] ?? 0);
        $arrears = (float) ($portfolio['totals']['tunggakan_pokok'] ?? 0);

        $detailKolek = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
        $ppapWajib = 0.0;
        foreach (($allowance['buckets'] ?? []) as $bucket) {
            $code = (int) ($bucket['code'] ?? 0);
            if ($code >= 1 && $code <= 5) {
                $detailKolek[$code] = round((float) ($bucket['outstanding'] ?? 0), 2);
            }
            $ppapWajib += (float) ($bucket['allowance_required'] ?? 0);
        }
        $ppapWajib = round($ppapWajib, 2);

        $problemLoans = round($detailKolek[3] + $detailKolek[4] + $detailKolek[5], 2);
        $productiveAssets = $totalAssets;
        $economicAssets = $totalAssets;

        $nplNeto = $outstanding > 0 ? round((($problemLoans - $ckp) / $outstanding) * 100, 2) : 0.0;
        $coveragePpap = $ppapWajib > 0 ? round(($ckp / $ppapWajib) * 100, 2) : 0.0;
        $roa = $productiveAssets > 0 ? round(($surplus / $productiveAssets) * 100, 2) : 0.0;
        $liquidityRatio = $currentLiabilities > 0 ? round(($cash / $currentLiabilities) * 100, 2) : 0.0;
        $solvabilityRatio = $totalLiabilities > 0 ? round(($totalAssets / $totalLiabilities) * 100, 2) : 0.0;
        $equityRatio = $paidInCapital > 0 ? round(($totalAssets / $paidInCapital) * 100, 2) : 0.0;

        $riskReceivableRatio = $outstanding > 0 ? round(($arrears / $outstanding) * 100, 2) : 0.0;
        $reserveRiskBase = abs($sumKolek) < 0.005 ? $ckp : $sumKolek;
        $reserveRatio = $reserveRiskBase > 0 ? round(($ckp / $reserveRiskBase) * 100, 2) : 0.0;
        $netProfitRatio = $productiveAssets > 0
            ? round((($surplus - ($sumKolek - $ckp)) / $productiveAssets) * 100, 2)
            : 0.0;
        // Legacy membulatkan tiga rasio POJK 19 ini ke bilangan bulat
        // sebelum membandingkan dengan ambang skor.
        $operatingExpenseRatio = ($expense == 0.0 || $revenue == 0.0)
            ? 0.0
            : round(($expense / $revenue) * 100);
        $receivableToEquityRatio = $economicAssets > 0
            ? round(($outstanding / $economicAssets) * 100)
            : 0.0;
        $netWorthRatio = $paidInCapital > 0
            ? round((($economicAssets - $sumKolek) / $paidInCapital) * 100)
            : round(($economicAssets - $sumKolek) * 100);

        $identity = [
            'legal_name' => (string) ($balance['identity']['legal_name'] ?? 'BUMDesma LKD'),
            'short_name' => $balance['identity']['short_name'] ?? null,
            'logo_url' => (string) ($balance['identity']['logo_url'] ?? ''),
            'registration_number' => (string) ($balance['identity']['registration_number'] ?? ''),
            'address' => (string) ($balance['identity']['address'] ?? ''),
            'district_name' => (string) ($balance['identity']['district_name'] ?? ''),
            'regency_name' => (string) ($balance['identity']['regency_name'] ?? ''),
            'phone' => (string) ($balance['identity']['phone'] ?? ''),
            'email' => (string) ($balance['identity']['email'] ?? ''),
            'manager_name' => (string) ($balance['identity']['manager_name'] ?? ''),
            'manager_title' => (string) ($balance['identity']['manager_title'] ?? ''),
            'treasurer_name' => (string) ($balance['identity']['treasurer_name'] ?? ''),
            'treasurer_title' => (string) ($balance['identity']['treasurer_title'] ?? ''),
        ];

        $profile = OrganizationProfile::query()->first();
        if ($profile !== null) {
            $identity['logo_url'] = (string) ($profile->logo_url ?? $identity['logo_url']);
            $identity['registration_number'] = (string) ($profile->registration_number ?? $identity['registration_number']);
            $identity['address'] = (string) ($profile->address ?? $identity['address']);
            $identity['district_name'] = (string) ($profile->district_name ?? $identity['district_name']);
            $identity['regency_name'] = (string) ($profile->regency_name ?? $identity['regency_name']);
            $identity['phone'] = (string) ($profile->phone ?? $identity['phone']);
            $identity['email'] = (string) ($profile->email ?? $identity['email']);
            $identity['manager_name'] = (string) ($profile->manager_name ?? $identity['manager_name']);
            $identity['manager_title'] = (string) ($profile->manager_title ?? $identity['manager_title']);
            $identity['treasurer_name'] = (string) ($profile->treasurer_name ?? $identity['treasurer_name']);
            $identity['treasurer_title'] = (string) ($profile->treasurer_title ?? $identity['treasurer_title']);
        }

        $payload = [
            'year' => $year,
            'month' => $month,
            'framework' => $framework,
            'framework_label' => $framework === '19'
                ? 'POJK NO. 19/POJK.05/2021'
                : 'POJK NO. 41/POJK.05/2024 & POJK NO. 49/POJK.05/2024',
            'title' => $framework === '19'
                ? 'PENILAIAN KESEHATAN OJK'
                : 'PENILAIAN TINGKAT KESEHATAN LKM',
            'period_label' => (string) ($balance['period']['period_label'] ?? ''),
            'as_of' => $asOf->toDateString(),
            'identity' => $identity,
            'values' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'equity' => $equity,
                'cash' => $cash,
                'current_liabilities' => $currentLiabilities,
                'paid_in_capital' => $paidInCapital,
                'revenue' => $revenue,
                'expense' => $expense,
                'surplus' => $surplus,
                'outstanding' => $outstanding,
                'arrears' => $arrears,
                'problem_loans' => $problemLoans,
                'ckp' => $ckp,
                'sum_kolek' => $sumKolek,
                'ppap_required' => $ppapWajib,
                'productive_assets' => $productiveAssets,
                'economic_assets' => $economicAssets,
                'npl_neto' => $nplNeto,
                'coverage_ppap' => $coveragePpap,
                'roa' => $roa,
                'liquidity_ratio' => $liquidityRatio,
                'solvability_ratio' => $solvabilityRatio,
                'equity_ratio' => $equityRatio,
                'risk_receivable_ratio' => $riskReceivableRatio,
                'reserve_ratio' => $reserveRatio,
                'net_profit_ratio' => $netProfitRatio,
                'operating_expense_ratio' => $operatingExpenseRatio,
                'receivable_to_equity_ratio' => $receivableToEquityRatio,
                'net_worth_ratio' => $netWorthRatio,
            ],
            'detail_kolek' => $detailKolek,
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
        ];

        return $framework === '19'
            ? $this->buildPojk19($payload)
            : $this->buildPojk41($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildPojk41(array $payload): array
    {
        $v = $payload['values'];
        $detail = $payload['detail_kolek'] ?? [];

        // Legacy POJK 41 memakai tarif PPAP 5% / 15% / 50% / 100%
        // untuk DPK / KL / Diragukan / Macet.
        $ppapPojk41 = round(
            ((float) ($detail[2] ?? 0) * 0.05)
            + ((float) ($detail[3] ?? 0) * 0.15)
            + ((float) ($detail[4] ?? 0) * 0.50)
            + ((float) ($detail[5] ?? 0) * 1.00),
            2,
        );
        $coveragePpap = $ppapPojk41 > 0
            ? round(((float) $v['ckp'] / $ppapPojk41) * 100, 2)
            : 0.0;

        $v['ppap_required'] = $ppapPojk41;
        $v['coverage_ppap'] = $coveragePpap;
        $payload['values'] = $v;

        $scoreSolvability = $v['solvability_ratio'] >= 110 ? 100.0 : ($v['solvability_ratio'] >= 100 ? 75.0 : 0.0);
        $scoreEquity = $v['equity_ratio'] >= 75 ? 100.0 : ($v['equity_ratio'] >= 50 ? 50.0 : 0.0);
        $scoreNpl = $v['npl_neto'] <= 5 ? 100.0 : ($v['npl_neto'] < 25 ? 50.0 : 0.0);
        $scoreCoverage = $coveragePpap >= 100 ? 100.0 : ($coveragePpap >= 50 ? 50.0 : 0.0);
        $scoreRoa = $v['roa'] > 0 ? 100.0 : 0.0;
        $scoreLiquidity = $v['liquidity_ratio'] >= 4 ? 100.0 : ($v['liquidity_ratio'] >= 3 ? 75.0 : 0.0);
        $scoreManagement = 75.0;

        $scoreCapital = ($scoreSolvability * 0.5) + ($scoreEquity * 0.5);
        $scoreAssetQuality = ($scoreNpl * 0.6) + ($scoreCoverage * 0.4);
        $composite = round(
            ($scoreCapital * 0.25)
            + ($scoreAssetQuality * 0.35)
            + ($scoreRoa * 0.10)
            + ($scoreLiquidity * 0.10)
            + ($scoreManagement * 0.20),
            2,
        );

        if ($composite >= 81) {
            $rating = 'PK 1 (Sangat Sehat)';
        } elseif ($composite >= 66) {
            $rating = 'PK 2 (Sehat)';
        } elseif ($composite >= 51) {
            $rating = 'PK 3 (Cukup Sehat)';
        } elseif ($composite >= 0) {
            $rating = 'PK 4 (Kurang Sehat)';
        } else {
            $rating = '-';
        }

        if ($v['npl_neto'] >= 25 || $v['equity_ratio'] < 50 || $v['coverage_ppap'] < 50) {
            $rating = 'PK 5 (Tidak Sehat)';
        }

        if ($rating === 'PK 5 (Tidak Sehat)') {
            $supervision = 'PENGAWASAN KHUSUS';
            $supervisionBasis = 'PK 5 / NPL Neto >= 25% / Ekuitas < 50% / Coverage PPAP < 50%';
        } elseif ($rating === 'PK 4 (Kurang Sehat)'
            || ($v['equity_ratio'] >= 50 && $v['equity_ratio'] < 75)
            || ($v['npl_neto'] > 5 && $v['npl_neto'] < 25)) {
            $supervision = 'PENGAWASAN INTENSIF';
            $supervisionBasis = 'PK 4 / Ekuitas 50%-<75% / NPL Neto >5%-<25%';
        } else {
            $supervision = 'PENGAWASAN NORMAL';
            $supervisionBasis = 'Seluruh rasio kuantitatif POJK terpenuhi & PK 1-3';
        }

        $payload['parameters'] = [
            ['label' => 'Total Aset', 'value' => $v['total_assets']],
            ['label' => 'Total Liabilitas', 'value' => $v['total_liabilities']],
            ['label' => 'Kas & Setara Kas', 'value' => $v['cash']],
            ['label' => 'Liabilitas Lancar', 'value' => $v['current_liabilities']],
            ['label' => 'Modal Disetor', 'value' => $v['paid_in_capital']],
            ['label' => 'Total Outstanding Pinjaman', 'value' => $v['outstanding']],
            ['label' => 'Sektor Bermasalah (KL + D + M)', 'value' => $v['problem_loans']],
            ['label' => 'PPAP yang Dibentuk (CKP)', 'value' => $v['ckp']],
        ];

        $payload['rows'] = [
            [
                'no' => 1,
                'aspect' => 'Permodalan',
                'ratio' => 'Solvabilitas',
                'result' => $v['solvability_ratio'],
                'unit' => '%',
                'score' => $scoreSolvability,
                'status' => $scoreSolvability >= 75 ? 'Memenuhi' : 'Tidak Memenuhi',
                'criteria' => ['≥ 110% skor 100', '≥ 100% skor 75', '< 100% skor 0'],
                'details' => [
                    ['label' => 'Total Aset', 'value' => $v['total_assets']],
                    ['label' => 'Total Liabilitas', 'value' => $v['total_liabilities']],
                ],
            ],
            [
                'no' => 2,
                'aspect' => 'Permodalan',
                'ratio' => 'Ekuitas / Modal',
                'result' => $v['equity_ratio'],
                'unit' => '%',
                'score' => $scoreEquity,
                'status' => $scoreEquity >= 75 ? 'Memenuhi' : 'Tidak Memenuhi',
                'criteria' => ['≥ 75% skor 100', '≥ 50% skor 50', '< 50% skor 0'],
                'details' => [
                    ['label' => 'Total Aset', 'value' => $v['total_assets']],
                    ['label' => 'Modal Disetor', 'value' => $v['paid_in_capital']],
                ],
            ],
            [
                'no' => 3,
                'aspect' => 'Kualitas Aset',
                'ratio' => 'NPL Neto',
                'result' => $v['npl_neto'],
                'unit' => '%',
                'score' => $scoreNpl,
                'status' => $scoreNpl >= 75 ? 'Memenuhi' : 'Tidak Memenuhi',
                'criteria' => ['≤ 5% skor 100', '< 25% skor 50', '≥ 25% skor 0'],
                'details' => [
                    ['label' => 'Piutang Bermasalah', 'value' => $v['problem_loans']],
                    ['label' => 'CKP', 'value' => $v['ckp']],
                    ['label' => 'Total Outstanding', 'value' => $v['outstanding']],
                ],
            ],
            [
                'no' => 4,
                'aspect' => 'Kualitas Aset',
                'ratio' => 'Coverage PPAP',
                'result' => $v['coverage_ppap'],
                'unit' => '%',
                'score' => $scoreCoverage,
                'status' => $scoreCoverage >= 75 ? 'Memenuhi' : 'Tidak Memenuhi',
                'criteria' => ['≥ 100% skor 100', '≥ 50% skor 50', '< 50% skor 0'],
                'details' => [
                    ['label' => 'CKP Dibentuk', 'value' => $v['ckp']],
                    ['label' => 'PPAP Wajib', 'value' => $v['ppap_required']],
                ],
            ],
            [
                'no' => 5,
                'aspect' => 'Rentabilitas',
                'ratio' => 'ROA',
                'result' => $v['roa'],
                'unit' => '%',
                'score' => $scoreRoa,
                'status' => $scoreRoa > 0 ? 'Positif' : 'Negatif',
                'criteria' => ['> 0% skor 100', '≤ 0% skor 0'],
                'details' => [
                    ['label' => 'Surplus / Laba', 'value' => $v['surplus']],
                    ['label' => 'Aset Produktif', 'value' => $v['productive_assets']],
                ],
            ],
            [
                'no' => 6,
                'aspect' => 'Likuiditas',
                'ratio' => 'Kas / Liabilitas Lancar',
                'result' => $v['liquidity_ratio'],
                'unit' => '%',
                'score' => $scoreLiquidity,
                'status' => $scoreLiquidity >= 75 ? 'Memenuhi' : 'Tidak Memenuhi',
                'criteria' => ['≥ 4 skor 100', '≥ 3 skor 75', '< 3 skor 0'],
                'details' => [
                    ['label' => 'Kas & Setara Kas', 'value' => $v['cash']],
                    ['label' => 'Liabilitas Lancar', 'value' => $v['current_liabilities']],
                ],
            ],
            [
                'no' => 7,
                'aspect' => 'Manajemen',
                'ratio' => 'Kualitatif',
                'result' => null,
                'unit' => '',
                'score' => $scoreManagement,
                'status' => 'Asumsi Normal',
                'criteria' => ['Asumsi normal skor 75'],
                'details' => [['label' => 'Penilaian', 'value' => null]],
            ],
        ];

        $payload['composite_score'] = $composite;
        $payload['composite_max'] = 100;
        $payload['rating'] = $rating;
        $payload['supervision_status'] = $supervision;
        $payload['supervision_basis'] = $supervisionBasis;
        $payload['summary_label'] = 'SKOR KOMPOSIT AKHIR';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildPojk19(array $payload): array
    {
        $v = $payload['values'];

        $riskScore = match (true) {
            $v['risk_receivable_ratio'] < 5 => [40.0, 'Sehat'],
            $v['risk_receivable_ratio'] < 10 => [35.0, 'Sehat'],
            $v['risk_receivable_ratio'] < 12.5 => [30.0, 'Cukup Sehat'],
            $v['risk_receivable_ratio'] < 15 => [25.0, 'Cukup Sehat'],
            $v['risk_receivable_ratio'] < 17.5 => [20.0, 'Kurang Sehat'],
            $v['risk_receivable_ratio'] < 20 => [15.0, 'Kurang Sehat'],
            $v['risk_receivable_ratio'] < 25 => [10.0, 'Tidak Sehat'],
            default => [0.0, 'Tidak Sehat'],
        };

        $reserveScore = match (true) {
            $v['reserve_ratio'] >= 100 => [20.0, 'Sehat'],
            $v['reserve_ratio'] > 90 => [15.0, 'Cukup Sehat'],
            $v['reserve_ratio'] > 80 => [12.5, 'Cukup Sehat'],
            $v['reserve_ratio'] > 70 => [10.0, 'Kurang Sehat'],
            $v['reserve_ratio'] > 60 => [7.5, 'Kurang Sehat'],
            $v['reserve_ratio'] > 50 => [5.0, 'Tidak Sehat'],
            default => [0.0, 'Tidak Sehat'],
        };

        $profitScore = match (true) {
            $v['net_profit_ratio'] >= 1 => [10.0, 'Sehat'],
            $v['net_profit_ratio'] > 0.75 => [8.75, 'Sehat'],
            $v['net_profit_ratio'] > 0.6 => [7.5, 'Cukup Sehat'],
            $v['net_profit_ratio'] > 0.45 => [6.25, 'Cukup Sehat'],
            $v['net_profit_ratio'] > 0.3 => [5.0, 'Kurang Sehat'],
            $v['net_profit_ratio'] > 0.15 => [3.75, 'Kurang Sehat'],
            $v['net_profit_ratio'] > 0 => [2.5, 'Tidak Sehat'],
            default => [0.0, 'Tidak Sehat'],
        };

        $opexScore = match (true) {
            $v['operating_expense_ratio'] < 60 => [10.0, 'Sehat'],
            $v['operating_expense_ratio'] < 65 => [8.75, 'Sehat'],
            $v['operating_expense_ratio'] < 70 => [5.75, 'Cukup Sehat'],
            $v['operating_expense_ratio'] < 75 => [6.25, 'Cukup Sehat'],
            $v['operating_expense_ratio'] < 80 => [5.75, 'Kurang Sehat'],
            $v['operating_expense_ratio'] < 85 => [3.75, 'Kurang Sehat'],
            $v['operating_expense_ratio'] < 95 => [2.5, 'Tidak Sehat'],
            default => [0.0, 'Tidak Sehat'],
        };

        $receivableScore = match (true) {
            $v['receivable_to_equity_ratio'] >= 85 => [10.0, 'Sehat'],
            $v['receivable_to_equity_ratio'] > 80 => [7.5, 'Cukup Sehat'],
            $v['receivable_to_equity_ratio'] > 75 => [6.25, 'Cukup Sehat'],
            $v['receivable_to_equity_ratio'] > 70 => [5.0, 'Kurang Sehat'],
            $v['receivable_to_equity_ratio'] > 65 => [3.75, 'Kurang Sehat'],
            default => [0.0, 'Tidak Sehat'],
        };

        $netWorthScore = match (true) {
            $v['net_worth_ratio'] >= 110 => [10.0, 'Sehat'],
            $v['net_worth_ratio'] > 107.5 => [8.75, 'Sehat'],
            $v['net_worth_ratio'] > 105 => [7.5, 'Cukup Sehat'],
            $v['net_worth_ratio'] > 102 => [6.25, 'Cukup Sehat'],
            $v['net_worth_ratio'] > 100 => [5.0, 'Kurang Sehat'],
            $v['net_worth_ratio'] > 97.5 => [3.75, 'Kurang Sehat'],
            $v['net_worth_ratio'] > 95 => [2.5, 'Tidak Sehat'],
            default => [0.0, 'Tidak Sehat'],
        };

        $rows = [
            [
                'no' => 1,
                'aspect' => 'Kualitas Aset',
                'ratio' => 'Rasio Saldo Piutang Berisiko',
                'result' => $v['risk_receivable_ratio'],
                'unit' => '%',
                'score' => $riskScore[0],
                'status' => $riskScore[1],
                'criteria' => ['<5% skor 40', '<10% skor 35', '<12.5% skor 30', '<15% skor 25', '<17.5% skor 20', '<20% skor 15', '<25% skor 10', '≥25% skor 0'],
                'details' => [
                    ['label' => 'a. Tunggakan', 'value' => $v['arrears']],
                    ['label' => 'b. Saldo piutang', 'value' => $v['outstanding']],
                ],
            ],
            [
                'no' => 2,
                'aspect' => 'Kualitas Aset',
                'ratio' => 'Rasio Cadangan Kerugian Piutang',
                'result' => $v['reserve_ratio'],
                'unit' => '%',
                'score' => $reserveScore[0],
                'status' => $reserveScore[1],
                'criteria' => ['≥100% skor 20', '>90% skor 15', '>80% skor 12.5', '>70% skor 10', '>60% skor 7.5', '>50% skor 5', '≤50% skor 0'],
                'details' => [
                    ['label' => 'a. CKP yang dimiliki', 'value' => $v['ckp']],
                    ['label' => 'b. Risiko kolektibilitas', 'value' => $v['sum_kolek']],
                ],
            ],
            [
                'no' => 3,
                'aspect' => 'Rentabilitas',
                'ratio' => 'Rasio Laba Bersih Terhadap Kekayaan UPK',
                'result' => $v['net_profit_ratio'],
                'unit' => '%',
                'score' => $profitScore[0],
                'status' => $profitScore[1],
                'criteria' => ['>1% skor 10', '>0.75% skor 8.75', '>0.6% skor 7.5', '>0.45% skor 6.25', '>0.3% skor 5', '>0.15% skor 3.75', '>0% skor 2.5', '≤0% skor 0'],
                'details' => [
                    ['label' => 'a. Laba berjalan', 'value' => $v['surplus']],
                    ['label' => 'b. Risiko kolektibilitas', 'value' => $v['sum_kolek']],
                    ['label' => 'c. CKP yang dimiliki', 'value' => $v['ckp']],
                    ['label' => 'd. Aset produktif', 'value' => $v['productive_assets']],
                ],
            ],
            [
                'no' => 4,
                'aspect' => 'Efisiensi',
                'ratio' => 'Rasio Beban Operasi',
                'result' => $v['operating_expense_ratio'],
                'unit' => '%',
                'score' => $opexScore[0],
                'status' => $opexScore[1],
                'criteria' => ['<60% skor 10', '<65% skor 8.75', '<70% skor 5.75', '<75% skor 6.25', '<80% skor 5.75', '<85% skor 3.75', '<95% skor 2.5', '≥95% skor 0'],
                'details' => [
                    ['label' => 'a. Akumulasi beban', 'value' => $v['expense']],
                    ['label' => 'b. Akumulasi pendapatan', 'value' => $v['revenue']],
                ],
            ],
            [
                'no' => 5,
                'aspect' => 'Likuiditas',
                'ratio' => 'Rasio Saldo Piutang Terhadap Kekayaan UPK Non Investasi',
                'result' => $v['receivable_to_equity_ratio'],
                'unit' => '%',
                'score' => $receivableScore[0],
                'status' => $receivableScore[1],
                'criteria' => ['≥85% skor 10', '>80% skor 7.5', '>75% skor 6.5', '>70% skor 5', '>65% skor 3.75', '>60% skor 2.5', '≤60% skor 0'],
                'details' => [
                    ['label' => 'a. Saldo piutang usaha', 'value' => $v['outstanding']],
                    ['label' => 'b. Aset produktif non investasi', 'value' => $v['economic_assets']],
                ],
            ],
            [
                'no' => 6,
                'aspect' => 'Permodalan',
                'ratio' => 'Rasio Kekayaan Bersih UPK',
                'result' => $v['net_worth_ratio'],
                'unit' => '%',
                'score' => $netWorthScore[0],
                'status' => $netWorthScore[1],
                'criteria' => ['≥110% skor 10', '>107.5% skor 8.75', '>105% skor 7.5', '>102% skor 6.25', '>100% skor 5', '>97.5% skor 3.75', '>95% skor 2.5', '<95% skor 0'],
                'details' => [
                    ['label' => 'a. Aset produktif non investasi', 'value' => $v['economic_assets']],
                    ['label' => 'b. Risiko kolektibilitas', 'value' => $v['sum_kolek']],
                    ['label' => 'c. Modal disetor UPK', 'value' => $v['paid_in_capital']],
                ],
            ],
        ];

        $composite = round(array_sum(array_map(static fn (array $row): float => (float) $row['score'], $rows)), 2);
        $rating = match (true) {
            $composite > 87.5 => 'Sehat',
            $composite > 62.5 => 'Cukup Sehat',
            $composite > 37.5 => 'Kurang Sehat',
            default => 'Tidak Sehat',
        };

        $payload['parameters'] = [
            ['label' => 'Total Aset Produktif', 'value' => $v['productive_assets']],
            ['label' => 'Total Aset Ekonomi', 'value' => $v['economic_assets']],
            ['label' => 'Modal Disetor', 'value' => $v['paid_in_capital']],
            ['label' => 'Total Outstanding Pinjaman', 'value' => $v['outstanding']],
            ['label' => 'Tunggakan Pokok', 'value' => $v['arrears']],
            ['label' => 'CKP / PPAP Dibentuk', 'value' => $v['ckp']],
            ['label' => 'Risiko Kolektibilitas', 'value' => $v['sum_kolek']],
            ['label' => 'Surplus / Laba Berjalan', 'value' => $v['surplus']],
        ];
        $payload['rows'] = $rows;
        $payload['composite_score'] = $composite;
        $payload['composite_max'] = 100;
        $payload['rating'] = $rating;
        $payload['supervision_status'] = null;
        $payload['supervision_basis'] = null;
        $payload['summary_label'] = 'KOMULATIF SKOR';

        return $payload;
    }

    private function paidInCapital(CarbonImmutable $asOf, float $fallbackEquity): float
    {
        try {
            $accounts = Account::query()
                ->whereIn('code', ['3.1.01.01', '3.1.01.02', '3.1.01.03'])
                ->get(['row_id', 'code', 'name', 'normal_balance', 'account_type']);

            $total = 0.0;
            foreach ($accounts as $account) {
                $total += (float) $this->balances->asOfRaw($account, $asOf)['signed'];
            }

            return abs($total) > 0.005 ? round($total, 2) : round($fallbackEquity, 2);
        } catch (Throwable) {
            return round($fallbackEquity, 2);
        }
    }
}
