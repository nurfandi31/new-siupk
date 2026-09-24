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
 * Kolektibilitas OJK v2 (KBP2).
 *
 * Varian dari KBP — format OJK dengan dimensi tambahan. Di sini
 * kolektibilitas di-breakdown per produk pinjaman, sehingga auditor
 * OJK bisa langsung melihat komposisi portofolio Lancar/DPK/KL/
 * Diragukan/Macet untuk masing-masing jenis produk (SPP, UEP,
 * Lembaga Lain, dll).
 *
 * Struktur output sama dengan CollectibilityOjkService untuk konsistensi,
 * kecuali `products` berisi array KBP per produk + `grand_total`.
 */
final readonly class CollectibilityOjkV2Service
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
     *   buckets_meta: list<array<string, mixed>>,
     *   products: list<array<string, mixed>>,
     *   grand_total: array<string, float|int>,
     *   borrower_scope: string,
     *   generated_at: string,
     *   tenant_id: int
     * }
     */
    public function buildReport(int $year, int $month, string $borrowerScope = 'all'): array
    {
        $borrowerScope = in_array($borrowerScope, ['all', 'group', 'member'], true) ? $borrowerScope : 'all';
        $tenantId = (int) ($this->context->id() ?? 0);

        $base = $this->collectibility->buildDesa($year, $month, 'all', $borrowerScope);

        $products = DB::connection('tenant')
            ->table('loan_products')
            ->where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name']);

        $loanMetrics = $this->collectLoanMetrics($year, $month, $borrowerScope);
        $byProduct = [];
        foreach ($products as $prod) {
            $byProduct[(int) $prod->row_id] = [
                'product_row_id' => (int) $prod->row_id,
                'product_code' => (string) $prod->code,
                'product_name' => (string) $prod->name,
                'loan_count_by_code' => array_fill(1, 5, 0),
                'outstanding_by_code' => array_fill(1, 5, 0.0),
                'loan_count' => 0,
                'outstanding' => 0.0,
            ];
        }

        foreach ($loanMetrics as $metric) {
            $code = $this->classifyDays((int) ($metric['days_overdue'] ?? 0));
            $productRowId = (int) ($metric['loan_product_row_id'] ?? 0);
            if (! isset($byProduct[$productRowId])) {
                continue;
            }
            $byProduct[$productRowId]['loan_count_by_code'][$code]++;
            $byProduct[$productRowId]['outstanding_by_code'][$code] += (float) $metric['outstanding'];
            $byProduct[$productRowId]['loan_count']++;
            $byProduct[$productRowId]['outstanding'] += (float) $metric['outstanding'];
        }

        $allowanceTotal = $this->fetchAllowanceTotal($year, $month);

        $productReports = [];
        $grand = [
            'loan_count' => 0,
            'outstanding' => 0.0,
            'allowance_required' => 0.0,
            'allowance_formed' => 0.0,
            'selisih' => 0.0,
        ];
        $grandTotalOutstanding = 0.0;

        foreach ($byProduct as $prod) {
            if ($prod['loan_count'] === 0) {
                continue;
            }
            $grandTotalOutstanding += (float) $prod['outstanding'];
        }

        foreach ($byProduct as $prod) {
            if ($prod['loan_count'] === 0) {
                continue;
            }
            $productOutstanding = (float) $prod['outstanding'];
            $share = $grandTotalOutstanding > 0 ? $productOutstanding / $grandTotalOutstanding : 0.0;
            $prodAllowanceFormed = round($allowanceTotal * $share, 2);

            $bucketRows = [];
            $prodRequired = 0.0;
            foreach (self::OJK_BUCKETS as $b) {
                $code = (int) $b['code'];
                $outstanding = round((float) $prod['outstanding_by_code'][$code], 2);
                $required = round($outstanding * (float) $b['rate'], 2);
                $subShare = $productOutstanding > 0 ? $outstanding / $productOutstanding : 0.0;
                $formedShare = round($prodAllowanceFormed * $subShare, 2);
                $pct = $productOutstanding > 0 ? round($outstanding / $productOutstanding * 100, 2) : 0.0;

                $bucketRows[] = [
                    'code' => $code,
                    'label' => $b['label'],
                    'days_label' => $this->daysLabel((int) $b['days_min'], $b['days_max']),
                    'rate_pct' => round((float) $b['rate'] * 100, 2),
                    'loan_count' => (int) $prod['loan_count_by_code'][$code],
                    'outstanding' => $outstanding,
                    'share_pct' => $pct,
                    'allowance_required' => $required,
                    'allowance_formed' => $formedShare,
                    'selisih' => round($formedShare - $required, 2),
                ];
                $prodRequired += $required;
            }

            $productReports[] = [
                'product_row_id' => $prod['product_row_id'],
                'product_code' => $prod['product_code'],
                'product_name' => $prod['product_name'],
                'loan_count' => (int) $prod['loan_count'],
                'outstanding' => round($productOutstanding, 2),
                'buckets' => $bucketRows,
                'allowance_required' => round($prodRequired, 2),
                'allowance_formed' => $prodAllowanceFormed,
                'selisih' => round($prodAllowanceFormed - $prodRequired, 2),
            ];

            $grand['loan_count'] += (int) $prod['loan_count'];
            $grand['outstanding'] = round(((float) $grand['outstanding']) + $productOutstanding, 2);
            $grand['allowance_required'] = round(((float) $grand['allowance_required']) + $prodRequired, 2);
            $grand['allowance_formed'] = round(((float) $grand['allowance_formed']) + $prodAllowanceFormed, 2);
        }
        $grand['selisih'] = round(((float) $grand['allowance_formed']) - ((float) $grand['allowance_required']), 2);

        $profile = OrganizationProfile::query()->first();

        $bucketsMeta = [];
        foreach (self::OJK_BUCKETS as $b) {
            $bucketsMeta[] = [
                'code' => (int) $b['code'],
                'label' => $b['label'],
                'days_label' => $this->daysLabel((int) $b['days_min'], $b['days_max']),
                'rate_pct' => round((float) $b['rate'] * 100, 2),
            ];
        }

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
            'buckets_meta' => $bucketsMeta,
            'products' => $productReports,
            'grand_total' => $grand,
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
            'tenant_id' => $tenantId,
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

        return $min.' – '.$max.' hari';
    }

    /**
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
                'loan_product_row_id' => (int) ($loan->loan_product_row_id ?? 0),
                'principal_amount' => (float) ($loan->principal_amount ?? 0),
                'outstanding' => $outstanding,
                'days_overdue' => $overdueMonths * 30,
                'overdue_months' => $overdueMonths,
            ];
        }

        return $rows;
    }

    private function fetchAllowanceTotal(int $year, int $month): float
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

        return round(max(0.0, $total), 2);
    }
}
