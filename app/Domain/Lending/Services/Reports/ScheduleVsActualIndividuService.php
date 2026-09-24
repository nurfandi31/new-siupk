<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rencana vs Realisasi angsuran per pinjaman INDIVIDU (legacy_source = 'member_loan').
 *
 * Baris laporan per angsuran: rencana vs realisasi pokok & jasa, plus selisih & status.
 * Level agregat: totals.plan_* / totals.actual_* / totals.gap_* / avg_delay_days / pct_realization.
 */
final class ScheduleVsActualIndividuService
{
    private const ACTIVE = ['active', 'disbursed', 'completed', 'written_off', 'rescheduled'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period: array{period_label: string, as_of: string},
     *   identity: array{legal_name: string, short_name: ?string},
     *   filters: array{year: int, month: int, loan_id: ?string, product: ?string},
     *   loans: list<array<string, mixed>>,
     *   totals: array<string, float|int>,
     *   monthLabels: array<int, string>,
     * }
     */
    public function build(int $year, int $month, ?string $loanId = null, ?string $productCode = null): array
    {
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));
        $from = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $until = $from->addMonth();
        $fromStr = $from->toDateString();
        $untilStr = $until->toDateString();
        $tenantId = $this->context->id();

        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        // Ambil daftar pinjaman INDIVIDU aktif (member_loan)
        $loansQuery = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('members as m', function ($j): void {
                $j->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('people as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'm.tenant_id')
                    ->on('v.row_id', '=', 'm.organization_unit_row_id');
            })
            ->leftJoin('loan_products as pr', function ($j): void {
                $j->on('pr.tenant_id', '=', 'l.tenant_id')
                    ->on('pr.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->where('l.legacy_source', 'member_loan')
            ->when($productCode !== null && $productCode !== 'all', function ($q) use ($productCode): void {
                $q->where('pr.code', $productCode);
            })
            ->when($loanId !== null && $loanId !== '', function ($q) use ($loanId): void {
                if (ctype_digit((string) $loanId)) {
                    $q->where('l.id', (int) $loanId);
                } else {
                    $q->where('l.loan_number', $loanId);
                }
            })
            ->orderBy('v.name')
            ->orderBy('p.full_name')
            ->orderBy('l.id')
            ->selectRaw('l.row_id, l.id as loan_local_id, l.loan_number, l.principal_amount, l.disbursed_at')
            ->selectRaw('p.full_name as member_name, m.member_number')
            ->selectRaw('v.name as village_name')
            ->selectRaw('pr.code as product_code, pr.name as product_name');

        $loans = $loansQuery->get();
        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->where('due_date', '>=', $fromStr)
                ->where('due_date', '<', $untilStr)
                ->orderBy('loan_row_id')
                ->orderBy('due_date')
                ->get([
                    'loan_row_id',
                    'installment_number',
                    'due_date',
                    'principal_due',
                    'interest_due',
                    'principal_paid',
                    'interest_paid',
                    'paid_at',
                    'status',
                ]);

        $instByLoan = $installments->groupBy('loan_row_id');

        $loanBlocks = [];

        $grandTotals = [
            'loan_count' => 0,
            'installment_count' => 0,
            'plan_principal' => 0.0,
            'plan_interest' => 0.0,
            'actual_principal' => 0.0,
            'actual_interest' => 0.0,
            'gap_principal' => 0.0,
            'gap_interest' => 0.0,
            'realized_count' => 0,
            'late_count' => 0,
            'total_delay_days' => 0,
        ];

        foreach ($loans as $loan) {
            $insts = $instByLoan->get($loan->row_id) ?? collect();
            if ($insts->isEmpty()) {
                continue;
            }

            $rowsOut = [];
            $loanTotals = [
                'plan_principal' => 0.0,
                'plan_interest' => 0.0,
                'actual_principal' => 0.0,
                'actual_interest' => 0.0,
                'gap_principal' => 0.0,
                'gap_interest' => 0.0,
                'realized_count' => 0,
                'late_count' => 0,
                'total_delay_days' => 0,
            ];

            foreach ($insts as $inst) {
                $planP = round((float) $inst->principal_due, 2);
                $planI = round((float) $inst->interest_due, 2);
                $actP = round((float) $inst->principal_paid, 2);
                $actI = round((float) $inst->interest_paid, 2);
                $gapP = round($planP - $actP, 2);
                $gapI = round($planI - $actI, 2);
                $dueDate = (string) $inst->due_date;
                $paidAt = $inst->paid_at !== null ? (string) $inst->paid_at : null;

                $delayDays = null;
                $isLate = false;
                $isPaid = (float) $inst->principal_paid + (float) $inst->interest_paid >= 0.005
                    || in_array((string) $inst->status, ['paid'], true);

                if ($isPaid && $paidAt !== null) {
                    $paidDay = CarbonImmutable::parse($paidAt)->startOfDay();
                    $dueDay = CarbonImmutable::parse($dueDate)->startOfDay();
                    if ($paidDay->greaterThan($dueDay)) {
                        $delayDays = (int) $dueDay->diffInDays($paidDay);
                        $isLate = true;
                    } else {
                        $delayDays = 0;
                    }
                }

                $status = 'Belum Bayar';
                if ($isPaid) {
                    $status = $isLate ? 'Terlambat' : 'Tepat Waktu';
                } elseif ($dueDate < CarbonImmutable::today()->toDateString()) {
                    $status = 'Lewat Jatuh Tempo';
                }

                $rowsOut[] = [
                    'installment_number' => (int) $inst->installment_number,
                    'due_date' => $dueDate,
                    'paid_at' => $paidAt,
                    'status' => (string) $inst->status,
                    'realization_status' => $status,
                    'plan_principal' => $planP,
                    'plan_interest' => $planI,
                    'actual_principal' => $actP,
                    'actual_interest' => $actI,
                    'gap_principal' => $gapP,
                    'gap_interest' => $gapI,
                    'delay_days' => $delayDays,
                    'is_late' => $isLate,
                    'is_paid' => $isPaid,
                ];

                $loanTotals['plan_principal'] = round($loanTotals['plan_principal'] + $planP, 2);
                $loanTotals['plan_interest'] = round($loanTotals['plan_interest'] + $planI, 2);
                $loanTotals['actual_principal'] = round($loanTotals['actual_principal'] + $actP, 2);
                $loanTotals['actual_interest'] = round($loanTotals['actual_interest'] + $actI, 2);
                $loanTotals['gap_principal'] = round($loanTotals['gap_principal'] + $gapP, 2);
                $loanTotals['gap_interest'] = round($loanTotals['gap_interest'] + $gapI, 2);
                if ($isPaid) {
                    $loanTotals['realized_count']++;
                    if ($isLate) {
                        $loanTotals['late_count']++;
                        if ($delayDays !== null) {
                            $loanTotals['total_delay_days'] += $delayDays;
                        }
                    }
                }
            }

            $totalPlan = round($loanTotals['plan_principal'] + $loanTotals['plan_interest'], 2);
            $totalActual = round($loanTotals['actual_principal'] + $loanTotals['actual_interest'], 2);
            $pctRealization = $totalPlan > 0.009 ? round(($totalActual / $totalPlan) * 100, 2) : 0.0;
            $avgDelay = $loanTotals['late_count'] > 0
                ? round($loanTotals['total_delay_days'] / $loanTotals['late_count'], 1)
                : 0.0;

            $loanBlocks[] = [
                'loan_row_id' => (int) $loan->row_id,
                'loan_id' => (int) $loan->loan_local_id,
                'loan_number' => $loan->loan_number ?: ('#'.$loan->loan_local_id),
                'member_name' => (string) ($loan->member_name ?? '—'),
                'member_number' => $loan->member_number,
                'village_name' => $loan->village_name ?: '—',
                'product_code' => $loan->product_code ?: '—',
                'product_name' => $loan->product_name ?: '—',
                'principal_amount' => (float) $loan->principal_amount,
                'disbursed_at' => $loan->disbursed_at ? substr((string) $loan->disbursed_at, 0, 10) : null,
                'rows' => $rowsOut,
                'totals' => [
                    'installment_count' => count($rowsOut),
                    'plan_principal' => $loanTotals['plan_principal'],
                    'plan_interest' => $loanTotals['plan_interest'],
                    'actual_principal' => $loanTotals['actual_principal'],
                    'actual_interest' => $loanTotals['actual_interest'],
                    'gap_principal' => $loanTotals['gap_principal'],
                    'gap_interest' => $loanTotals['gap_interest'],
                    'realized_count' => $loanTotals['realized_count'],
                    'late_count' => $loanTotals['late_count'],
                    'total_delay_days' => $loanTotals['total_delay_days'],
                    'avg_delay_days' => $avgDelay,
                    'pct_realization' => $pctRealization,
                ],
            ];

            $grandTotals['loan_count']++;
            $grandTotals['installment_count'] += count($rowsOut);
            $grandTotals['plan_principal'] = round($grandTotals['plan_principal'] + $loanTotals['plan_principal'], 2);
            $grandTotals['plan_interest'] = round($grandTotals['plan_interest'] + $loanTotals['plan_interest'], 2);
            $grandTotals['actual_principal'] = round($grandTotals['actual_principal'] + $loanTotals['actual_principal'], 2);
            $grandTotals['actual_interest'] = round($grandTotals['actual_interest'] + $loanTotals['actual_interest'], 2);
            $grandTotals['gap_principal'] = round($grandTotals['gap_principal'] + $loanTotals['gap_principal'], 2);
            $grandTotals['gap_interest'] = round($grandTotals['gap_interest'] + $loanTotals['gap_interest'], 2);
            $grandTotals['realized_count'] += $loanTotals['realized_count'];
            $grandTotals['late_count'] += $loanTotals['late_count'];
            $grandTotals['total_delay_days'] += $loanTotals['total_delay_days'];
        }

        $grandPlan = round($grandTotals['plan_principal'] + $grandTotals['plan_interest'], 2);
        $grandActual = round($grandTotals['actual_principal'] + $grandTotals['actual_interest'], 2);
        $grandTotals['pct_realization'] = $grandPlan > 0.009 ? round(($grandActual / $grandPlan) * 100, 2) : 0.0;
        $grandTotals['avg_delay_days'] = $grandTotals['late_count'] > 0
            ? round($grandTotals['total_delay_days'] / $grandTotals['late_count'], 1)
            : 0.0;

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        return [
            'year' => $year,
            'month' => $month,
            'period' => [
                'period_label' => ($labels[$month] ?? "Bulan {$month}")." {$year}",
                'as_of' => $until->subDay()->toDateString(),
            ],
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
            ],
            'filters' => [
                'year' => $year,
                'month' => $month,
                'loan_id' => $loanId,
                'product' => $productCode,
            ],
            'loans' => $loanBlocks,
            'totals' => $grandTotals,
            'monthLabels' => $labels,
        ];
    }
}
