<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * DRPL — Rincian Pinjaman Lunas Kelompok (OJK).
 *
 * Laporan regulasi OJK yang menampilkan pinjaman kelompok (`legacy_source`
 * bernilai NULL atau 'group_loan') yang telah dilunasi (status = 'completed').
 * Setiap baris memuat nomor kontrak, nama kelompok, desa, pokok, total
 * dibayar, tanggal cair, tanggal lunas, lama pinjam, dan bagi hasil/bunga.
 *
 * Filter: periode pelunasan (completed_at) per bulan/tahun.
 */
final class PaidGroupLoansService
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
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function buildReport(int $year, int $month): array
    {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

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
            ->leftJoin('organization_units as gv', function ($j): void {
                $j->on('gv.tenant_id', '=', 'g.tenant_id')
                    ->on('gv.row_id', '=', 'g.organization_unit_row_id');
            })
            ->leftJoin('loan_products as lp', function ($j): void {
                $j->on('lp.tenant_id', '=', 'l.tenant_id')
                    ->on('lp.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.status', 'completed')
            ->where(function ($q): void {
                $q->whereNull('l.legacy_source')
                    ->orWhere('l.legacy_source', 'group_loan');
            })
            ->whereBetween('l.completed_at', [$startOfMonth, $endOfMonth])
            ->orderBy('l.completed_at')
            ->orderBy('l.id')
            ->selectRaw('l.row_id, l.id, l.loan_number, l.principal_amount, l.interest_rate, l.disbursed_at, l.completed_at, l.term_months, lp.code as product_code, lp.name as product_name')
            ->selectRaw('g.row_id as group_row_id, g.code as group_code, g.name as group_name, gv.name as village_name')
            ->get();

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        // Total pembayaran per loan
        $paymentTotals = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->selectRaw('loan_row_id, sum(amount) as total_paid')
                ->groupBy('loan_row_id')
                ->pluck('total_paid', 'loan_row_id');

        // Total bunga yang dibayar (interest_paid) per loan
        $interestTotals = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->selectRaw('loan_row_id, sum(interest_paid) as total_interest, sum(penalty_paid) as total_penalty')
                ->groupBy('loan_row_id')
                ->get()
                ->keyBy('loan_row_id');

        $rows = [];
        $totals = [
            'count' => 0,
            'principal_total' => 0.0,
            'paid_amount_total' => 0.0,
            'interest_total' => 0.0,
        ];

        foreach ($loans as $loan) {
            $disbursedAt = $loan->disbursed_at ? (string) $loan->disbursed_at : null;
            $completedAt = $loan->completed_at ? (string) $loan->completed_at : null;

            $loanMonths = null;
            if ($disbursedAt !== null && $completedAt !== null) {
                try {
                    $start = CarbonImmutable::parse($disbursedAt);
                    $end = CarbonImmutable::parse($completedAt);
                    $loanMonths = max(1, (int) round($start->floatDiffInMonths($end)));
                } catch (\Throwable) {
                    $loanMonths = null;
                }
            }

            $principal = (float) $loan->principal_amount;
            $totalPaid = (float) ($paymentTotals[$loan->row_id] ?? 0);

            $instRow = $interestTotals->get($loan->row_id);
            $interestPaid = $instRow !== null ? (float) ($instRow->total_interest ?? 0) : 0.0;

            $rows[] = [
                'loan_id' => (int) $loan->id,
                'loan_row_id' => (int) $loan->row_id,
                'loan_number' => (string) ($loan->loan_number ?? ''),
                'group_code' => (string) ($loan->group_code ?? ''),
                'group_name' => (string) ($loan->group_name ?? '—'),
                'village_name' => $loan->village_name ?? null,
                'product_code' => (string) ($loan->product_code ?? ''),
                'product_name' => (string) ($loan->product_name ?? ''),
                'principal_amount' => $principal,
                'total_paid' => round($totalPaid, 2),
                'interest_paid' => round($interestPaid, 2),
                'disbursed_at' => $disbursedAt,
                'completed_at' => $completedAt,
                'loan_months' => $loanMonths,
                'interest_rate' => (float) ($loan->interest_rate ?? 0),
            ];

            $totals['count']++;
            $totals['principal_total'] = round($totals['principal_total'] + $principal, 2);
            $totals['paid_amount_total'] = round($totals['paid_amount_total'] + $totalPaid, 2);
            $totals['interest_total'] = round($totals['interest_total'] + $interestPaid, 2);
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
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
