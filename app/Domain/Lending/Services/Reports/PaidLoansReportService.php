<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Daftar Pinjaman Lunas — laporan pinjaman yang telah lunas pada bulan tertentu.
 *
 * Mengambil pinjaman berstatus 'completed' (sudah divalidasi pelunasan) yang
 * completed_at-nya jatuh dalam periode filter. Menampilkan data peminjam,
 * kelompok (untuk pinjaman kelompok), desa, pokok, tanggal cair/lunas, lama
 * pinjam, dan jumlah angsuran.
 */
final class PaidLoansReportService
{
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
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildReport(int $year, int $month, ?string $borrowerScope = null): array
    {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $profile = OrganizationProfile::query()->first();

        // Ambil pinjaman lunas dalam periode. Pinjaman dianggap "lunas" ketika
        // status = 'completed' (lihat LoanService::complete()) dan completed_at
        // berada dalam rentang bulan filter.
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
            ->where('l.status', 'completed')
            ->whereBetween('l.completed_at', [$startOfMonth, $endOfMonth])
            ->orderBy('l.completed_at')
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
            ->selectRaw('l.row_id, l.id, l.loan_number, l.principal_amount, l.disbursed_at, l.completed_at, l.term_months, l.legacy_source, lp.code as product_code, lp.name as product_name')
            ->selectRaw('g.row_id as group_row_id, g.code as group_code, g.name as group_name, gv.name as group_village_name')
            ->selectRaw('m.row_id as member_row_id, m.member_number, p.full_name as member_name, p.national_identity_number as nik, mv.name as member_village_name')
            ->get();

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        // Ambil jumlah angsuran per loan (paid installments)
        $installmentCounts = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->where('status', 'paid')
                ->selectRaw('loan_row_id, count(*) as paid_count')
                ->groupBy('loan_row_id')
                ->pluck('paid_count', 'loan_row_id');

        // Ambil total pembayaran (realisasi total) per loan
        $paymentTotals = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->selectRaw('loan_row_id, sum(amount) as total_paid')
                ->groupBy('loan_row_id')
                ->pluck('total_paid', 'loan_row_id');

        $rows = [];
        $totals = [
            'count' => 0,
            'principal_total' => 0.0,
            'group_count' => 0,
            'member_count' => 0,
            'paid_amount' => 0.0,
            'installments_total' => 0,
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

            $disbursedAt = $loan->disbursed_at ? (string) $loan->disbursed_at : null;
            $completedAt = $loan->completed_at ? (string) $loan->completed_at : null;

            // Lama pinjam dalam hari (dari pencairan sampai lunas)
            $loanDays = null;
            if ($disbursedAt !== null && $completedAt !== null) {
                try {
                    $start = CarbonImmutable::parse($disbursedAt);
                    $end = CarbonImmutable::parse($completedAt);
                    $loanDays = max(0, $start->diffInDays($end));
                } catch (\Throwable) {
                    $loanDays = null;
                }
            }

            $principal = (float) $loan->principal_amount;
            $paidCount = (int) ($installmentCounts[$loan->row_id] ?? 0);
            $totalPaid = (float) ($paymentTotals[$loan->row_id] ?? 0);

            $rows[] = [
                'loan_id' => (int) $loan->id,
                'loan_row_id' => (int) $loan->row_id,
                'loan_number' => (string) ($loan->loan_number ?? ''),
                'borrower_kind' => $isMember ? 'Individu' : 'Kelompok',
                'borrower_name' => $borrowerName,
                'borrower_code' => $borrowerCode,
                'nik' => $isMember ? ($loan->nik ?? null) : null,
                'group_name' => $isMember ? null : ($loan->group_name ?? null),
                'village_name' => $villageName,
                'product_code' => (string) ($loan->product_code ?? ''),
                'product_name' => (string) ($loan->product_name ?? ''),
                'principal_amount' => $principal,
                'disbursed_at' => $disbursedAt,
                'completed_at' => $completedAt,
                'loan_days' => $loanDays,
                'installment_count' => $paidCount,
                'total_paid' => round($totalPaid, 2),
            ];

            $totals['count']++;
            $totals['principal_total'] += $principal;
            $totals['installments_total'] += $paidCount;
            $totals['paid_amount'] += $totalPaid;
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
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
            ],
            'borrower_scope' => $borrowerScope,
            'rows' => $rows,
            'totals' => [
                'count' => $totals['count'],
                'principal_total' => round($totals['principal_total'], 2),
                'group_count' => $totals['group_count'],
                'member_count' => $totals['member_count'],
                'installments_total' => $totals['installments_total'],
                'paid_amount' => round($totals['paid_amount'], 2),
            ],
        ];
    }
}
