<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * DRP — Daftar Rincian Pinjaman Aktif (OJK).
 *
 * Laporan regulasi OJK yang menampilkan daftar lengkap pinjaman yang masih
 * aktif (status = 'active' / 'disbursed'), baik pinjaman kelompok maupun
 * pinjaman individu. Setiap baris memuat nomor kontrak, tanggal cair,
 * peminjam, desa, pokok, sisa pokok, angsuran ke-, tanggal jatuh tempo
 * berikutnya, dan kolektibilitas.
 *
 * Sumber data utama: tabel `loans`, `loan_installments`, `loan_borrowers`,
 * `groups`, `members`, `people`, `organization_units`, dan `loan_products`.
 *
 * Filter:
 *   - Periode (year, month) → berdasarkan disbursed_at bulan tersebut
 *   - borrower_scope (all/group/member) → filter jenis pinjaman
 *   - collectibility (all/current/overdue) → filter kolektibilitas
 */
final class ActiveLoansService
{
    private const ACTIVE_STATUSES = ['active', 'disbursed'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string},
     *   borrower_scope: ?string,
     *   collectibility: string,
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildReport(int $year, int $month, ?string $borrowerScope = null, string $collectibility = 'all'): array
    {
        $tenantId = $this->context->id();
        $asOfDate = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = $asOfDate->toDateString();
        $asOfStr = $asOfDate->toDateString();

        if (! in_array($collectibility, ['all', 'current', 'overdue'], true)) {
            $collectibility = 'all';
        }

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        $loansQuery = DB::connection('tenant')
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
            ->leftJoin('loan_products as lp', function ($j): void {
                $j->on('lp.tenant_id', '=', 'l.tenant_id')
                    ->on('lp.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE_STATUSES)
            ->whereBetween('l.disbursed_at', [$startOfMonth, $endOfMonth])
            ->orderBy('l.disbursed_at')
            ->orderBy('l.id');

        if ($borrowerScope === 'group') {
            $loansQuery->where(function ($q): void {
                $q->whereNull('l.legacy_source')
                    ->orWhere('l.legacy_source', 'group_loan');
            });
        } elseif ($borrowerScope === 'member') {
            $loansQuery->where('l.legacy_source', 'member_loan');
        }

        $loans = $loansQuery
            ->selectRaw('l.row_id, l.id, l.loan_number, l.principal_amount, l.interest_rate, l.tenor, l.disbursed_at, l.term_months, l.legacy_source, lp.code as product_code, lp.name as product_name')
            ->selectRaw('g.row_id as group_row_id, g.code as group_code, g.name as group_name, gv.name as group_village_name')
            ->selectRaw('m.row_id as member_row_id, m.member_number, p.full_name as member_name, p.national_identity_number as nik, mv.name as member_village_name')
            ->get();

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        // Ambil installment summary: paid count, next due, overdue
        $installments = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->orderBy('installment_number')
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
                    'status',
                ])
                ->groupBy('loan_row_id');

        $rows = [];
        $totals = [
            'count' => 0,
            'principal_total' => 0.0,
            'principal_remaining_total' => 0.0,
            'interest_remaining_total' => 0.0,
            'overdue_count' => 0,
            'overdue_amount_total' => 0.0,
            'group_count' => 0,
            'member_count' => 0,
        ];

        foreach ($loans as $loan) {
            $isMember = ($loan->legacy_source ?? null) === 'member_loan';

            $borrowerName = $isMember
                ? (string) ($loan->member_name ?? '—')
                : (string) ($loan->group_name ?? '—');
            $borrowerCode = $isMember
                ? ($loan->member_number ?? null)
                : ($loan->group_code ?? null);
            $villageName = $isMember
                ? ($loan->member_village_name ?? null)
                : ($loan->group_village_name ?? null);

            // Hitung summary installments
            $insts = $installments->get($loan->row_id) ?? collect();
            $paidCount = 0;
            $nextDue = null;
            $oldestOverdueDue = null;
            $principalRemaining = 0.0;
            $interestRemaining = 0.0;
            $overduePrincipal = 0.0;
            $overdueInterest = 0.0;
            $overduePenalty = 0.0;

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
                $interestRemaining = round($interestRemaining + $iRem, 2);

                $dueDate = (string) $inst->due_date;
                $lineRem = round($pRem + $iRem + $penRem, 2);
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

                if ((string) $inst->status === 'paid' || $pRem <= 0.009) {
                    $paidCount++;
                }
            }

            $overdueAmount = round($overduePrincipal + $overdueInterest + $overduePenalty, 2);
            $isOverdue = $overdueAmount > 0.009;

            // Apply collectibility filter
            if ($collectibility === 'current' && $isOverdue) {
                continue;
            }
            if ($collectibility === 'overdue' && ! $isOverdue) {
                continue;
            }

            // Skip loans with no remaining principal and no remaining interest (effectively closed but status not yet updated)
            if ($principalRemaining <= 0.009 && $interestRemaining <= 0.009) {
                continue;
            }

            $daysOverdue = 0;
            if ($oldestOverdueDue !== null) {
                $daysOverdue = max(0, (int) CarbonImmutable::parse($oldestOverdueDue)->startOfDay()->diffInDays($asOfDate->startOfDay()));
            }

            // Kolektibilitas OJK (5 kolektibilitas)
            $collectibilityCode = $this->collectibilityCode($daysOverdue);

            $rows[] = [
                'loan_id' => (int) $loan->id,
                'loan_row_id' => (int) $loan->row_id,
                'loan_number' => (string) ($loan->loan_number ?? ''),
                'borrower_kind' => $isMember ? 'Individu' : 'Kelompok',
                'borrower_name' => $borrowerName,
                'borrower_code' => $borrowerCode,
                'nik' => $isMember ? ($loan->nik ?? null) : null,
                'village_name' => $villageName,
                'product_code' => (string) ($loan->product_code ?? ''),
                'product_name' => (string) ($loan->product_name ?? ''),
                'principal_amount' => round((float) $loan->principal_amount, 2),
                'principal_remaining' => $principalRemaining,
                'interest_remaining' => $interestRemaining,
                'overdue_amount' => $overdueAmount,
                'days_overdue' => $daysOverdue,
                'collectibility_code' => $collectibilityCode,
                'collectibility_label' => $this->collectibilityLabel($collectibilityCode),
                'disbursed_at' => $loan->disbursed_at ? substr((string) $loan->disbursed_at, 0, 10) : null,
                'next_due_date' => $nextDue,
                'installment_paid' => $paidCount,
                'tenor_months' => (int) ($loan->term_months ?? 0),
            ];

            $totals['count']++;
            $totals['principal_total'] = round($totals['principal_total'] + (float) $loan->principal_amount, 2);
            $totals['principal_remaining_total'] = round($totals['principal_remaining_total'] + $principalRemaining, 2);
            $totals['interest_remaining_total'] = round($totals['interest_remaining_total'] + $interestRemaining, 2);
            $totals['overdue_amount_total'] = round($totals['overdue_amount_total'] + $overdueAmount, 2);
            if ($isOverdue) {
                $totals['overdue_count']++;
            }
            if ($isMember) {
                $totals['member_count']++;
            } else {
                $totals['group_count']++;
            }
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => ($monthNames[$month] ?? "Bulan {$month}")." {$year}",
            'as_of' => $asOfStr,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
            ],
            'borrower_scope' => $borrowerScope,
            'collectibility' => $collectibility,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    private function collectibilityCode(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return 'L';
        }
        if ($daysOverdue <= 30) {
            return 'DPK1';
        }
        if ($daysOverdue <= 60) {
            return 'DPK2';
        }
        if ($daysOverdue <= 90) {
            return 'DPK3';
        }

        return 'MACET';
    }

    private function collectibilityLabel(string $code): string
    {
        return match ($code) {
            'L' => 'Lancar',
            'DPK1' => 'Dalam Perhatian Khusus 1',
            'DPK2' => 'Dalam Perhatian Khusus 2',
            'DPK3' => 'Dalam Perhatian Khusus 3',
            'MACET' => 'Macet',
            default => '—',
        };
    }
}
