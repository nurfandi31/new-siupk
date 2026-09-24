<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Daftar tagihan angsuran yang jatuh tempo pada tanggal tertentu (default: hari ini).
 *
 * Definisi: installment.due_date = asOf, status installment belum lunas (pending/partial),
 * dan loan masih aktif (active/disbursed).
 */
final class DueTodayReportService
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

        $rows = DB::connection('tenant')
            ->table('loan_installments as i')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'i.tenant_id')
                    ->on('l.row_id', '=', 'i.loan_row_id');
            })
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
            ->where('i.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE)
            ->whereIn('i.status', self::OPEN_INSTALLMENT_STATUSES)
            ->where('i.due_date', '=', $asOfStr)
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
            ->orderBy('i.installment_number')
            ->selectRaw('i.row_id as installment_row_id, i.installment_number, i.due_date, i.status as installment_status, i.principal_due, i.interest_due, i.penalty_due, i.principal_paid, i.interest_paid')
            ->selectRaw('l.row_id as loan_row_id, l.id as loan_local_id, l.loan_number, l.legacy_source, l.principal_amount, l.disbursed_at')
            ->selectRaw('g.name as group_name')
            ->selectRaw('p.full_name as member_name, m.member_number')
            ->selectRaw('COALESCE(gv.name, mv.name) as village_name')
            ->selectRaw('pr.code as product_code, pr.name as product_name')
            ->get();

        $out = [];
        $totals = [
            'count' => 0,
            'loan_count' => 0,
            'principal_due' => 0.0,
            'interest_due' => 0.0,
            'penalty_due' => 0.0,
            'total_due' => 0.0,
            'principal_paid' => 0.0,
            'interest_paid' => 0.0,
            'remaining_principal' => 0.0,
            'remaining_interest' => 0.0,
        ];

        $loanKeys = [];

        foreach ($rows as $r) {
            $pDue = (float) $r->principal_due;
            $iDue = (float) $r->interest_due;
            $penDue = (float) $r->penalty_due;
            $pPaid = (float) $r->principal_paid;
            $iPaid = (float) $r->interest_paid;
            $pRem = max(0.0, round($pDue - $pPaid, 2));
            $iRem = max(0.0, round($iDue - $iPaid, 2));
            $lineTotal = round($pDue + $iDue + $penDue, 2);

            $legacySource = (string) ($r->legacy_source ?? '');
            $borrowerName = $legacySource === 'member_loan'
                ? (string) ($r->member_name ?? '—')
                : (string) ($r->group_name ?? '—');
            $borrowerCode = $legacySource === 'member_loan'
                ? (string) ($r->member_number ?? '')
                : '';

            $out[] = [
                'installment_row_id' => (int) $r->installment_row_id,
                'installment_number' => (int) $r->installment_number,
                'loan_row_id' => (int) $r->loan_row_id,
                'loan_id' => (int) $r->loan_local_id,
                'loan_number' => $r->loan_number ?: ('#'.$r->loan_local_id),
                'legacy_source' => $legacySource,
                'borrower_name' => $borrowerName,
                'borrower_code' => $borrowerCode,
                'village_name' => $r->village_name ?: '—',
                'product_code' => $r->product_code ?: '—',
                'product_name' => $r->product_name ?: '—',
                'due_date' => (string) $r->due_date,
                'days_overdue' => 0,
                'installment_status' => (string) $r->installment_status,
                'principal_due' => $pDue,
                'interest_due' => $iDue,
                'penalty_due' => $penDue,
                'total_due' => $lineTotal,
                'principal_paid' => $pPaid,
                'interest_paid' => $iPaid,
                'remaining_principal' => $pRem,
                'remaining_interest' => $iRem,
            ];

            $totals['count']++;
            $totals['principal_due'] = round($totals['principal_due'] + $pDue, 2);
            $totals['interest_due'] = round($totals['interest_due'] + $iDue, 2);
            $totals['penalty_due'] = round($totals['penalty_due'] + $penDue, 2);
            $totals['total_due'] = round($totals['total_due'] + $lineTotal, 2);
            $totals['principal_paid'] = round($totals['principal_paid'] + $pPaid, 2);
            $totals['interest_paid'] = round($totals['interest_paid'] + $iPaid, 2);
            $totals['remaining_principal'] = round($totals['remaining_principal'] + $pRem, 2);
            $totals['remaining_interest'] = round($totals['remaining_interest'] + $iRem, 2);

            $loanKeys[$r->loan_row_id] = true;
        }

        $totals['loan_count'] = count($loanKeys);

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        return [
            'as_of' => $asOfStr,
            'period' => [
                'period_label' => 'Tagihan jatuh tempo '.$asOfDate->translatedFormat('d F Y'),
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
        ];
    }

    private function resolveAsOf(?string $asOf): CarbonImmutable
    {
        if ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) === 1) {
            return CarbonImmutable::parse($asOf)->startOfDay();
        }

        return CarbonImmutable::today();
    }
}
