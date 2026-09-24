<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Daftar tunggakan (overdue) — pinjaman aktif yang memiliki installment
 * due_date < asOf dengan sisa pokok/belum lunas.
 *
 * Definisi: installment.due_date < asOf, line.sisa > 0, loan.status aktif.
 * Output satu baris per loan dengan agregat tunggakan & max days_overdue.
 */
final class OverdueReportService
{
    private const ACTIVE = ['active', 'disbursed'];

    private const OPEN_INSTALLMENT_STATUSES = ['pending', 'partial'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   as_of: string,
     *   period: array{period_label: string, as_of: string},
     *   identity: array{legal_name: string, short_name: ?string},
     *   filters: array{as_of: ?string, scope: ?string},
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>,
     *   aging: list<array{key: string, label: string, count: int, principal: float, overdue: float}>,
     * }
     */
    public function build(?string $asOf = null, ?string $borrowerScope = null): array
    {
        $asOfDate = $this->resolveAsOf($asOf);
        $asOfStr = $asOfDate->toDateString();
        $tenantId = $this->context->id();
        $borrowerScope = $borrowerScope !== null && in_array($borrowerScope, ['group', 'member'], true)
            ? $borrowerScope
            : null;

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
            ->leftJoin('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->leftJoin('organization_units as gv', function ($j): void {
                $j->on('gv.tenant_id', '=', 'g.tenant_id')
                    ->on('gv.row_id', '=', 'g.organization_unit_row_id');
            })
            ->leftJoin('organization_units as mv', function ($j): void {
                $j->on('mv.tenant_id', '=', 'm.tenant_id')
                    ->on('mv.row_id', '=', 'm.organization_unit_row_id');
            })
            ->leftJoin('loan_products as pr', function ($j): void {
                $j->on('pr.tenant_id', '=', 'l.tenant_id')
                    ->on('pr.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->when($borrowerScope === 'group', function ($q): void {
                $q->where(function ($w): void {
                    $w->whereNull('l.legacy_source')
                        ->orWhere('l.legacy_source', 'group_loan');
                });
            })
            ->when($borrowerScope === 'member', function ($q): void {
                $q->where('l.legacy_source', 'member_loan');
            })
            ->orderBy('village_name')
            ->orderBy('borrower_name')
            ->orderBy('l.id')
            ->selectRaw('l.row_id, l.id as loan_local_id, l.loan_number, l.legacy_source, l.principal_amount, l.disbursed_at')
            ->selectRaw('g.name as group_name')
            ->selectRaw('p.full_name as member_name, m.member_number')
            ->selectRaw('COALESCE(gv.name, mv.name) as village_name')
            ->selectRaw('pr.code as product_code, pr.name as product_name')
            ->get();

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->whereIn('status', self::OPEN_INSTALLMENT_STATUSES)
                ->where('due_date', '<', $asOfStr)
                ->orderBy('loan_row_id')
                ->orderBy('due_date')
                ->get([
                    'loan_row_id',
                    'installment_number',
                    'due_date',
                    'principal_due',
                    'principal_paid',
                    'interest_due',
                    'interest_paid',
                    'penalty_due',
                    'penalty_paid',
                ]);

        $instByLoan = $installments->groupBy('loan_row_id');

        $out = [];
        $totals = [
            'loan_count' => 0,
            'principal_disbursed' => 0.0,
            'principal_remaining' => 0.0,
            'overdue_principal' => 0.0,
            'overdue_interest' => 0.0,
            'overdue_penalty' => 0.0,
            'overdue_amount' => 0.0,
            'overdue_installment_count' => 0,
            'max_days_overdue' => 0,
            'avg_days_overdue' => 0.0,
        ];

        $agingAcc = [
            '1_30' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            '31_60' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            '61_90' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
            '90_plus' => ['count' => 0, 'principal' => 0.0, 'overdue' => 0.0],
        ];

        $daysSum = 0;
        $daysCount = 0;

        foreach ($loans as $loan) {
            $insts = $instByLoan->get($loan->row_id) ?? collect();
            if ($insts->isEmpty()) {
                continue;
            }

            $overduePrincipal = 0.0;
            $overdueInterest = 0.0;
            $overduePenalty = 0.0;
            $principalRemaining = 0.0;
            $oldestDue = null;
            $installmentCount = 0;

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

                $principalRemaining = round($principalRemaining + $pRem, 2);

                $overduePrincipal = round($overduePrincipal + $pRem, 2);
                $overdueInterest = round($overdueInterest + $iRem, 2);
                $overduePenalty = round($overduePenalty + $penRem, 2);
                $installmentCount++;

                $dueDate = (string) $inst->due_date;
                if ($oldestDue === null || $dueDate < $oldestDue) {
                    $oldestDue = $dueDate;
                }
            }

            $overdueAmount = round($overduePrincipal + $overdueInterest + $overduePenalty, 2);
            if ($overdueAmount <= 0.009) {
                continue;
            }

            $daysOverdue = max(0, (int) CarbonImmutable::parse($oldestDue)->startOfDay()->diffInDays($asOfDate->startOfDay()));
            $bucket = $this->agingBucket($daysOverdue);

            $legacySource = (string) ($loan->legacy_source ?? '');
            $borrowerName = $legacySource === 'member_loan'
                ? (string) ($loan->member_name ?? '—')
                : (string) ($loan->group_name ?? '—');
            $borrowerCode = $legacySource === 'member_loan'
                ? (string) ($loan->member_number ?? '')
                : '';

            $out[] = [
                'loan_row_id' => (int) $loan->row_id,
                'loan_id' => (int) $loan->loan_local_id,
                'loan_number' => $loan->loan_number ?: ('#'.$loan->loan_local_id),
                'legacy_source' => $legacySource,
                'borrower_name' => $borrowerName,
                'borrower_code' => $borrowerCode,
                'village_name' => $loan->village_name ?: '—',
                'product_code' => $loan->product_code ?: '—',
                'product_name' => $loan->product_name ?: '—',
                'disbursed_at' => $loan->disbursed_at ? substr((string) $loan->disbursed_at, 0, 10) : null,
                'principal_disbursed' => (float) $loan->principal_amount,
                'principal_remaining' => $principalRemaining,
                'overdue_principal' => $overduePrincipal,
                'overdue_interest' => $overdueInterest,
                'overdue_penalty' => $overduePenalty,
                'overdue_amount' => $overdueAmount,
                'overdue_installment_count' => $installmentCount,
                'oldest_due_date' => $oldestDue,
                'days_overdue' => $daysOverdue,
                'aging_bucket' => $bucket,
                'collectibility' => $this->collectibility($daysOverdue),
            ];

            $totals['loan_count']++;
            $totals['principal_disbursed'] = round($totals['principal_disbursed'] + (float) $loan->principal_amount, 2);
            $totals['principal_remaining'] = round($totals['principal_remaining'] + $principalRemaining, 2);
            $totals['overdue_principal'] = round($totals['overdue_principal'] + $overduePrincipal, 2);
            $totals['overdue_interest'] = round($totals['overdue_interest'] + $overdueInterest, 2);
            $totals['overdue_penalty'] = round($totals['overdue_penalty'] + $overduePenalty, 2);
            $totals['overdue_amount'] = round($totals['overdue_amount'] + $overdueAmount, 2);
            $totals['overdue_installment_count'] += $installmentCount;
            $totals['max_days_overdue'] = max($totals['max_days_overdue'], $daysOverdue);

            $daysSum += $daysOverdue;
            $daysCount++;

            $agingAcc[$bucket]['count']++;
            $agingAcc[$bucket]['principal'] = round($agingAcc[$bucket]['principal'] + $principalRemaining, 2);
            $agingAcc[$bucket]['overdue'] = round($agingAcc[$bucket]['overdue'] + $overdueAmount, 2);
        }

        $totals['avg_days_overdue'] = $daysCount > 0 ? round($daysSum / $daysCount, 1) : 0.0;

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        return [
            'as_of' => $asOfStr,
            'period' => [
                'period_label' => 'Posisi tunggakan per '.$asOfDate->translatedFormat('d F Y'),
                'as_of' => $asOfStr,
            ],
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
            ],
            'filters' => [
                'as_of' => $asOf,
                'scope' => $borrowerScope,
            ],
            'rows' => $out,
            'totals' => $totals,
            'aging' => [
                ['key' => '1_30', 'label' => '1–30 hari', 'count' => $agingAcc['1_30']['count'], 'principal' => $agingAcc['1_30']['principal'], 'overdue' => $agingAcc['1_30']['overdue']],
                ['key' => '31_60', 'label' => '31–60 hari', 'count' => $agingAcc['31_60']['count'], 'principal' => $agingAcc['31_60']['principal'], 'overdue' => $agingAcc['31_60']['overdue']],
                ['key' => '61_90', 'label' => '61–90 hari', 'count' => $agingAcc['61_90']['count'], 'principal' => $agingAcc['61_90']['principal'], 'overdue' => $agingAcc['61_90']['overdue']],
                ['key' => '90_plus', 'label' => '> 90 hari', 'count' => $agingAcc['90_plus']['count'], 'principal' => $agingAcc['90_plus']['principal'], 'overdue' => $agingAcc['90_plus']['overdue']],
            ],
        ];
    }

    private function resolveAsOf(?string $asOf): CarbonImmutable
    {
        if ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) === 1) {
            return CarbonImmutable::parse($asOf)->startOfDay();
        }

        return CarbonImmutable::today();
    }

    private function agingBucket(int $daysOverdue): string
    {
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

    private function collectibility(int $daysOverdue): string
    {
        if ($daysOverdue <= 30) {
            return 'Lancar';
        }
        if ($daysOverdue <= 90) {
            return 'Kurang Lancar';
        }
        if ($daysOverdue <= 180) {
            return 'Diragukan';
        }

        return 'Macet';
    }
}
