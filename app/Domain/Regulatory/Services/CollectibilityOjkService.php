<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Lending\Services\Reports\CollectibilityReportService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kolektibilitas OJK v1 (KBP).
 *
 * Format OJK yang diminta: 5 golongan kolektibilitas berdasarkan hari
 * keterlambatan, dengan kolom Cadangan (Penyisihan CKPN) sesuai tarif
 * standar (0.5% / 3% / 10% / 50% / 100%).
 *
 * Perbedaan dengan CollectibilityReportService::buildDesa() existing:
 *  - Pembagian 5 golongan (Lancar / DPK / KL / Diragukan / Macet)
 *    bukan 3 golongan (Lancar / Diragukan / Macet).
 *  - Kolom tambahan: Cadangan Wajib (% x outstanding) + Cadangan
 *    Dibentuk (saldo akun 1.1.04.*).
 *  - Dikelompokkan per produk pinj. + Grand Total, dengan filter
 *    borrower scope (all/group/member) sesuai kebutuhan OJK.
 *
 * Penghitungan hari keterlambatan: mengikuti cara existing -
 * proporsi tunggakan pokok / rata-rata angsuran bulanan.
 */
final readonly class CollectibilityOjkService
{
    private const ACTIVE = ['active', 'disbursed'];

    /** @var list<array{code: int, label: string, days_min: int, days_max: int|null, rate: float}> */
    private const OJK_BUCKETS = [
        ['code' => 1, 'label' => 'Lancar',                    'days_min' => 0,  'days_max' => 0,    'rate' => 0.005],
        ['code' => 2, 'label' => 'Dalam Perhatian Khusus',    'days_min' => 1,  'days_max' => 90,   'rate' => 0.03],
        ['code' => 3, 'label' => 'Kurang Lancar',             'days_min' => 91, 'days_max' => 120,  'rate' => 0.10],
        ['code' => 4, 'label' => 'Diragukan',                 'days_min' => 121, 'days_max' => 180,  'rate' => 0.50],
        ['code' => 5, 'label' => 'Macet',                     'days_min' => 181, 'days_max' => null, 'rate' => 1.00],
    ];

    public function __construct(
        private CollectibilityReportService $collectibility,
        private AccountBalanceQuery $balances,
        private TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   as_of: string,
     *   identity: array<string, string|null>,
     *   buckets: list<array<string, mixed>>,
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>,
     *   borrower_scope: string,
     *   generated_at: string,
     *   tenant_id: int
     * }
     */
    public function buildReport(int $year, int $month, string $borrowerScope = 'all'): array
    {
        $borrowerScope = in_array($borrowerScope, ['all', 'group', 'member'], true) ? $borrowerScope : 'all';

        $base = $this->collectibility->buildDesa($year, $month, 'all', $borrowerScope);

        $loanMetrics = $this->collectLoanMetrics($year, $month, $borrowerScope);
        $outstandingByBucket = array_fill(1, 5, 0.0);
        $loanCountByBucket = array_fill(1, 5, 0);
        $totalOutstanding = 0.0;
        $totalLoans = 0;

        foreach ($loanMetrics as $metric) {
            $code = $this->classifyDays((int) ($metric['days_overdue'] ?? 0));
            $outstandingByBucket[$code] += (float) ($metric['outstanding'] ?? 0);
            $loanCountByBucket[$code]++;
            $totalOutstanding += (float) ($metric['outstanding'] ?? 0);
            $totalLoans++;
        }

        $allowanceFormed = $this->fetchAllowanceFormed($year, $month);
        $allowanceTotal = (float) $allowanceFormed['total'];

        $buckets = [];
        $totals = [
            'loan_count' => 0,
            'outstanding' => 0.0,
            'allowance_required' => 0.0,
            'allowance_formed' => 0.0,
            'selisih' => 0.0,
        ];

        foreach (self::OJK_BUCKETS as $b) {
            $code = (int) $b['code'];
            $outstanding = round((float) $outstandingByBucket[$code], 2);
            $allowanceReq = round($outstanding * (float) $b['rate'], 2);
            $pct = $totalOutstanding > 0 ? round($outstanding / $totalOutstanding * 100, 2) : 0.0;
            // Distribusi proporsional dari total cadangan yang sudah
            // dibentuk ke tiap golongan (share of outstanding x total).
            $share = $totalOutstanding > 0 ? $outstanding / $totalOutstanding : 0.0;
            $formedShare = round($allowanceTotal * $share, 2);

            $buckets[] = [
                'code' => $code,
                'label' => $b['label'],
                'days_min' => $b['days_min'],
                'days_max' => $b['days_max'],
                'days_label' => $this->daysLabel((int) $b['days_min'], $b['days_max']),
                'rate' => (float) $b['rate'],
                'rate_pct' => round((float) $b['rate'] * 100, 2),
                'loan_count' => (int) $loanCountByBucket[$code],
                'outstanding' => $outstanding,
                'share_pct' => $pct,
                'allowance_required' => $allowanceReq,
                'allowance_formed' => $formedShare,
                'selisih' => round($formedShare - $allowanceReq, 2),
            ];

            $totals['loan_count'] += (int) $loanCountByBucket[$code];
            $totals['outstanding'] = round($totals['outstanding'] + $outstanding, 2);
            $totals['allowance_required'] = round($totals['allowance_required'] + $allowanceReq, 2);
            $totals['allowance_formed'] = round($totals['allowance_formed'] + $formedShare, 2);
        }

        $totals['selisih'] = round($totals['allowance_formed'] - $totals['allowance_required'], 2);

        $profile = OrganizationProfile::query()->first();

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => (string) ($base['period_label'] ?? ''),
            'as_of' => CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString(),
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
                'logo_url' => $profile?->logo_url,
                'registration_number' => (string) ($profile?->registration_number ?? ''),
                'address' => (string) ($profile?->address ?? ''),
                'district_name' => (string) ($profile?->district_name ?? ''),
                'regency_name' => (string) ($profile?->regency_name ?? ''),
                'manager_name' => (string) ($profile?->manager_name ?? ''),
                'manager_title' => (string) ($profile?->manager_title ?? ''),
                'treasurer_name' => (string) ($profile?->treasurer_name ?? ''),
                'treasurer_title' => (string) ($profile?->treasurer_title ?? ''),
            ],
            'borrower_scope' => $borrowerScope,
            'buckets' => $buckets,
            'rows' => $loanMetrics,
            'totals' => $totals,
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
            'tenant_id' => (int) ($this->context->id() ?? 0),
        ];
    }

    public function build(int $year, int $month, string $borrowerScope = 'all'): array
    {
        return $this->buildReport($year, $month, $borrowerScope);
    }

    private function classifyDays(int $days): int
    {
        foreach (self::OJK_BUCKETS as $b) {
            $min = (int) $b['days_min'];
            $max = $b['days_max'] === null ? PHP_INT_MAX : (int) $b['days_max'];
            if ($days >= $min && $days <= $max) {
                return (int) $b['code'];
            }
        }

        return 5;
    }

    private function daysLabel(int $min, ?int $max): string
    {
        if ($min === 0 && $max === 0) {
            return '0 hari';
        }
        if ($max === null) {
            return '> '.$min.' hari';
        }

        return $min.' - '.$max.' hari';
    }

    /**
     * Hitung days_overdue + outstanding per loan dengan as-of akhir bulan.
     *
     * @return list<array<string, mixed>>
     */
    private function collectLoanMetrics(int $year, int $month, string $borrowerScope): array
    {
        $tenantId = (int) ($this->context->id() ?? 0);
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $loansQuery = DB::connection('tenant')
            ->table('loans as l')
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE);

        if ($borrowerScope === 'group') {
            $loansQuery->where(function ($q): void {
                $q->whereNull('l.legacy_source')
                    ->orWhere('l.legacy_source', 'group_loan');
            });
        } elseif ($borrowerScope === 'member') {
            $loansQuery->where('l.legacy_source', 'member_loan');
        }

        $loans = $loansQuery
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.principal_amount',
                'l.loan_product_row_id',
                'l.disbursed_at',
            ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->get(['loan_row_id', 'due_date', 'principal_due', 'interest_due']);

        $allocations = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payment_allocations as a')
                ->join('loan_payments as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'a.tenant_id')
                        ->on('p.row_id', '=', 'a.payment_row_id');
                })
                ->where('a.tenant_id', $tenantId)
                ->whereIn('p.loan_row_id', $loanRowIds)
                ->where('p.paid_at', '<=', $endOfMonth)
                ->get(['p.loan_row_id', 'a.component', 'a.amount']);

        $instByLoan = $installments->groupBy('loan_row_id');
        $allocByLoan = $allocations->groupBy('loan_row_id');

        $rows = [];
        foreach ($loans as $loan) {
            $loanInsts = $instByLoan->get($loan->row_id) ?? collect();
            $loanAllocs = $allocByLoan->get($loan->row_id) ?? collect();

            $principalPaid = (float) $loanAllocs->where('component', 'principal')->sum('amount');
            $principalDue = (float) $loanInsts->where('due_date', '<=', $endOfMonth)->sum('principal_due');
            $outstanding = round(max(0.0, $principalDue - $principalPaid), 2);

            $overdueMonths = 0;
            if ($loanInsts->count() > 0 && $loanInsts->where('due_date', '<=', $endOfMonth)->count() > 0) {
                $avgMonthlyInst = (float) ($loan->principal_amount ?? 0) / max(1, $loanInsts->count());
                $tunggakanPokok = max(0.0, round($principalDue - $principalPaid, 2));
                $overdueMonths = $avgMonthlyInst > 0 ? (int) floor($tunggakanPokok / $avgMonthlyInst) : 0;
            }

            $rows[] = [
                'loan_row_id' => (int) $loan->row_id,
                'loan_id' => (int) $loan->id,
                'loan_number' => (string) ($loan->loan_number ?? ''),
                'principal_amount' => (float) ($loan->principal_amount ?? 0),
                'outstanding' => $outstanding,
                'days_overdue' => $overdueMonths * 30,
                'overdue_months' => $overdueMonths,
                'disbursed_at' => $loan->disbursed_at,
            ];
        }

        return $rows;
    }

    /**
     * Cadangan yang sudah dibentuk - baca dari akun 1.1.04.* (Cadangan
     * Kerugian Piutang) sesuai saldo YTD. Karena COA tidak punya
     * pemisahan per kolektibilitas, distribusi proporsional ke tiap
     * golongan berdasarkan share outstanding - di sini kita hanya
     * kembalikan total agregat; pemerataan per golongan dilakukan di
     * buildReport() dengan memperhatikan rasio outstanding.
     *
     * @return array{by_code: array<int, float>, total: float}
     */
    private function fetchAllowanceFormed(int $year, int $month): array
    {
        $asOf = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->startOfDay();

        $accounts = Account::query()
            ->where('account_type', 'asset')
            ->where(function ($q): void {
                $q->where('code', '1.1.04.01')
                    ->orWhere('code', '1.1.04.02')
                    ->orWhere('code', '1.1.04.03')
                    ->orWhere('code', '1.1.04.04')
                    ->orWhere('code', '1.1.04.05')
                    ->orWhere('code', '1.1.04.06')
                    ->orWhere('code', '1.1.04.07')
                    ->orWhere('code', '1.1.04.08')
                    ->orWhere('code', '1.1.04.09');
            })
            ->get(['row_id', 'code', 'name', 'normal_balance']);

        $total = 0.0;
        foreach ($accounts as $account) {
            $raw = $this->balances->asOfRaw($account, $asOf);
            $total += (float) $raw['signed'];
        }
        $total = round(max(0.0, $total), 2);

        return ['by_code' => [], 'total' => $total];
    }
}
