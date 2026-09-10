<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Outstanding + tunggakan per pinjaman aktif (harian).
 * SoT sisa = Σ (due − paid) di loan_installments.
 */
final class LoanPortfolioReportService
{
    private const ACTIVE = ['active', 'disbursed'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   as_of: string,
     *   period: array{period_label: string, as_of: string},
     *   identity: array{legal_name: string, short_name: ?string},
     *   filter: string,
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>,
     *   aging: list<array{key: string, label: string, count: int, principal: float, overdue: float}>
     * }
     */
    public function build(?string $asOf = null, string $filter = 'all'): array
    {
        $asOfDate = $this->resolveAsOf($asOf);
        $asOfStr = $asOfDate->toDateString();
        $tenantId = $this->context->id();
        $filter = in_array($filter, ['all', 'overdue', 'current'], true) ? $filter : 'all';

        $loans = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->leftJoin('loan_products as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'l.tenant_id')
                    ->on('p.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->orderBy('v.name')
            ->orderBy('g.name')
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.status',
                'l.disbursed_at',
                'l.principal_amount',
                'g.name as group_name',
                'v.name as village_name',
                'p.code as product_code',
                'p.name as product_name',
            ]);

        if ($loans->isEmpty()) {
            return $this->empty($asOfStr, $filter);
        }

        $loanIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = DB::connection('tenant')
            ->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->whereIn('loan_row_id', $loanIds)
            ->orderBy('installment_number')
            ->get([
                'loan_row_id',
                'due_date',
                'principal_due',
                'principal_paid',
                'interest_due',
                'interest_paid',
                'penalty_due',
                'penalty_paid',
            ])
            ->groupBy('loan_row_id');

        $rows = [];
        $totals = [
            'count' => 0,
            'principal_disbursed' => 0.0,
            'principal_remaining' => 0.0,
            'interest_remaining' => 0.0,
            'overdue_amount' => 0.0,
            'overdue_principal' => 0.0,
            'overdue_interest' => 0.0,
            'overdue_count' => 0,
        ];
        $agingAcc = [
            'current' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            '1_30' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            '31_60' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            '61_90' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            '90_plus' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
        ];
        /** @var array<string, array{village:string,count:int,principal:float,overdue:float,overdue_count:int}> $byVillage */
        $byVillage = [];

        foreach ($loans as $loan) {
            $insts = $installments->get($loan->row_id) ?? collect();
            $principalRemaining = 0.0;
            $interestRemaining = 0.0;
            $overduePrincipal = 0.0;
            $overdueInterest = 0.0;
            $overduePenalty = 0.0;
            $nextDue = null;
            $oldestOverdueDue = null;

            foreach ($insts as $inst) {
                $pDue = (float) $inst->principal_due;
                $pPaid = (float) $inst->principal_paid;
                $iDue = (float) $inst->interest_due;
                $iPaid = (float) $inst->interest_paid;
                $penDue = (float) $inst->penalty_due;
                $penPaid = (float) $inst->penalty_paid;

                $pRem = max(0.0, round($pDue - $pPaid, 2));
                $iRem = max(0.0, round($iDue - $iPaid, 2));
                $penRem = max(0.0, round($penDue - $penPaid, 2));
                $lineRem = round($pRem + $iRem + $penRem, 2);

                $principalRemaining = round($principalRemaining + $pRem, 2);
                $interestRemaining = round($interestRemaining + $iRem, 2);

                $dueDate = (string) $inst->due_date;
                if ($lineRem > 0.009 && $dueDate < $asOfStr) {
                    $overduePrincipal = round($overduePrincipal + $pRem, 2);
                    $overdueInterest = round($overdueInterest + $iRem, 2);
                    $overduePenalty = round($overduePenalty + $penRem, 2);
                    if ($oldestOverdueDue === null || $dueDate < $oldestOverdueDue) {
                        $oldestOverdueDue = $dueDate;
                    }
                }

                if ($lineRem > 0.009 && ($nextDue === null || $dueDate < $nextDue)) {
                    $nextDue = $dueDate;
                }
            }

            $overdueAmount = round($overduePrincipal + $overdueInterest + $overduePenalty, 2);

            // Skip fully paid residual actives (same as list tab lunas heuristic).
            if ($principalRemaining <= 0.009 && $interestRemaining <= 0.009) {
                continue;
            }

            $daysOverdue = 0;
            if ($oldestOverdueDue !== null) {
                // due < asOf always here; absolute day count is enough.
                $daysOverdue = max(0, (int) CarbonImmutable::parse($oldestOverdueDue)->startOfDay()->diffInDays($asOfDate->startOfDay()));
            }

            $isOverdue = $overdueAmount > 0.009;
            if ($filter === 'overdue' && ! $isOverdue) {
                continue;
            }
            if ($filter === 'current' && $isOverdue) {
                continue;
            }

            $bucket = $this->agingBucket($daysOverdue, $isOverdue);
            $agingAcc[$bucket]['count']++;
            $agingAcc[$bucket]['principal'] = round($agingAcc[$bucket]['principal'] + $principalRemaining, 2);
            $agingAcc[$bucket]['overdue'] = round($agingAcc[$bucket]['overdue'] + $overdueAmount, 2);

            $disbursed = (float) ($loan->principal_amount ?? 0);
            $village = $loan->village_name ? (string) $loan->village_name : '—';
            $rows[] = [
                'row_id' => (int) $loan->row_id,
                'id' => (int) $loan->id,
                'loan_number' => $loan->loan_number ?: ('#'.$loan->id),
                'group_name' => $loan->group_name ?: '—',
                'village_name' => $village,
                'product_code' => $loan->product_code ?: '—',
                'product_name' => $loan->product_name ?: '—',
                'disbursed_at' => $loan->disbursed_at ? substr((string) $loan->disbursed_at, 0, 10) : null,
                'principal_disbursed' => round($disbursed, 2),
                'principal_remaining' => $principalRemaining,
                'interest_remaining' => $interestRemaining,
                'overdue_amount' => $overdueAmount,
                'overdue_principal' => $overduePrincipal,
                'overdue_interest' => $overdueInterest,
                'days_overdue' => $daysOverdue,
                'next_due_date' => $nextDue,
                'aging_bucket' => $bucket,
                'status' => (string) $loan->status,
            ];

            if (! isset($byVillage[$village])) {
                $byVillage[$village] = [
                    'village' => $village,
                    'count' => 0,
                    'principal' => 0.0,
                    'overdue' => 0.0,
                    'overdue_count' => 0,
                ];
            }
            $byVillage[$village]['count']++;
            $byVillage[$village]['principal'] = round($byVillage[$village]['principal'] + $principalRemaining, 2);
            $byVillage[$village]['overdue'] = round($byVillage[$village]['overdue'] + $overdueAmount, 2);
            if ($isOverdue) {
                $byVillage[$village]['overdue_count']++;
            }

            $totals['count']++;
            $totals['principal_disbursed'] = round($totals['principal_disbursed'] + $disbursed, 2);
            $totals['principal_remaining'] = round($totals['principal_remaining'] + $principalRemaining, 2);
            $totals['interest_remaining'] = round($totals['interest_remaining'] + $interestRemaining, 2);
            $totals['overdue_amount'] = round($totals['overdue_amount'] + $overdueAmount, 2);
            $totals['overdue_principal'] = round($totals['overdue_principal'] + $overduePrincipal, 2);
            $totals['overdue_interest'] = round($totals['overdue_interest'] + $overdueInterest, 2);
            if ($isOverdue) {
                $totals['overdue_count']++;
            }
        }

        ksort($byVillage);

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        return [
            'as_of' => $asOfStr,
            'period' => [
                'period_label' => 'Posisi per '.$asOfDate->translatedFormat('d F Y'),
                'as_of' => $asOfStr,
            ],
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
            ],
            'filter' => $filter,
            'rows' => $rows,
            'totals' => $totals,
            'aging' => [
                ['key' => 'current', 'label' => 'Lancar (0)', 'count' => $agingAcc['current']['count'], 'principal' => $agingAcc['current']['principal'], 'overdue' => $agingAcc['current']['overdue']],
                ['key' => '1_30', 'label' => '1–30 hari', 'count' => $agingAcc['1_30']['count'], 'principal' => $agingAcc['1_30']['principal'], 'overdue' => $agingAcc['1_30']['overdue']],
                ['key' => '31_60', 'label' => '31–60 hari', 'count' => $agingAcc['31_60']['count'], 'principal' => $agingAcc['31_60']['principal'], 'overdue' => $agingAcc['31_60']['overdue']],
                ['key' => '61_90', 'label' => '61–90 hari', 'count' => $agingAcc['61_90']['count'], 'principal' => $agingAcc['61_90']['principal'], 'overdue' => $agingAcc['61_90']['overdue']],
                ['key' => '90_plus', 'label' => '> 90 hari', 'count' => $agingAcc['90_plus']['count'], 'principal' => $agingAcc['90_plus']['principal'], 'overdue' => $agingAcc['90_plus']['overdue']],
            ],
            'by_village' => array_values($byVillage),
        ];
    }

    private function resolveAsOf(?string $asOf): CarbonImmutable
    {
        if ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) === 1) {
            return CarbonImmutable::parse($asOf)->startOfDay();
        }

        return CarbonImmutable::today();
    }

    private function agingBucket(int $daysOverdue, bool $isOverdue): string
    {
        if (! $isOverdue || $daysOverdue <= 0) {
            return 'current';
        }
        if ($daysOverdue <= 30) {
            return '1_30';
        }
        if ($daysOverdue <= 60) {
            return '31_60';
        }
        if ($daysOverdue <= 90) {
            return '61_90';
        }

        return '90_plus';
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $asOfStr, string $filter): array
    {
        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);
        $asOfDate = CarbonImmutable::parse($asOfStr);

        return [
            'as_of' => $asOfStr,
            'period' => [
                'period_label' => 'Posisi per '.$asOfDate->translatedFormat('d F Y'),
                'as_of' => $asOfStr,
            ],
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
            ],
            'filter' => $filter,
            'rows' => [],
            'totals' => [
                'count' => 0,
                'principal_disbursed' => 0.0,
                'principal_remaining' => 0.0,
                'interest_remaining' => 0.0,
                'overdue_amount' => 0.0,
                'overdue_principal' => 0.0,
                'overdue_interest' => 0.0,
                'overdue_count' => 0,
            ],
            'aging' => [
                ['key' => 'current', 'label' => 'Lancar (0)', 'count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
                ['key' => '1_30', 'label' => '1–30 hari', 'count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
                ['key' => '31_60', 'label' => '31–60 hari', 'count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
                ['key' => '61_90', 'label' => '61–90 hari', 'count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
                ['key' => '90_plus', 'label' => '> 90 hari', 'count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            ],
            'by_village' => [],
        ];
    }
}
