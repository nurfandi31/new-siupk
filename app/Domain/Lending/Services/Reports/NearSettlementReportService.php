<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pinjaman Mendekati Jatuh Tempo (Near Settlement).
 *
 * Definisi operasional: pinjaman AKTIF yang memiliki 1–3 angsuran
 * belum jatuh tempo tersisa (remaining_tenor <= 3), di mana angsuran
 * berikutnya jatuh tempo dalam rentang `as_of` s.d. `as_of + horizon_days`.
 *
 * Tujuan laporan:
 *   - Memberi early-warning bagi tim collection & treasury.
 *   - Antisipasi cash flow: piutang yang tersisa tinggal 1–3 angsuran
 *     artinya hampir seluruh modal akan kembali dalam waktu dekat.
 *
 * Output:
 *   - Daftar baris pinjaman (No, Loan Number, Peminjam/Kelompok, Pokok,
 *     Sisa Pokok, Angsuran ke-, Sisa Tenor, Tgl Jatuh Tempo Berikutnya,
 *     Tgl Estimasi Lunas, Status).
 *   - KPI ringkasan (jumlah, total sisa pokok, rata-rata sisa tenor).
 *   - Disortir ascending by `next_due_date` (yang paling dekat jatuh tempo
 *     tampil di atas).
 */
final class NearSettlementReportService
{
    private const ACTIVE = ['active', 'disbursed'];

    /** Horizon default dalam hari — pinjaman yang next_due <= as_of + horizon. */
    public const DEFAULT_HORIZON_DAYS = 90;

    /** Batas atas remaining_tenor untuk masuk laporan. */
    public const MAX_REMAINING_TENOR = 3;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Bangun laporan near-settlement.
     *
     * @param  string  $asOf  Tanggal acuan (YYYY-MM-DD).
     * @param  string|null  $borrowerScope  null|'group'|'member'.
     * @param  int  $horizonDays  Batas hari ke depan dari as_of untuk due_date.
     * @return array<string, mixed>
     */
    public function buildReport(
        string $asOf,
        ?string $borrowerScope = null,
        int $horizonDays = self::DEFAULT_HORIZON_DAYS,
    ): array {
        $tenantId = $this->context->id();
        $asOfDate = $this->resolveAsOf($asOf);
        $horizonEnd = $asOfDate->addDays(max(1, $horizonDays));

        $profile = OrganizationProfile::query()->first();

        // 1. Ambil pinjaman AKTIF sesuai scope.
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
            ->selectRaw('l.row_id, l.id, l.loan_number, l.principal_amount, l.disbursed_at, l.term_months, l.legacy_source, lp.code as product_code, lp.name as product_name')
            ->selectRaw('g.row_id as group_row_id, g.code as group_code, g.name as group_name, gv.name as group_village_name')
            ->selectRaw('m.row_id as member_row_id, m.member_number, p.full_name as member_name, p.national_identity_number as nik, mv.name as member_village_name')
            ->orderBy('l.id')
            ->get();

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        if ($loanRowIds === []) {
            return $this->emptyResult($asOfDate, $horizonDays, $borrowerScope, $profile);
        }

        // 2. Ambil semua installments (untuk hitung sisa tenor & installment berikutnya).
        //    Kita pakai agregasi per loan di SQL agar hemat row.
        $installmentStats = DB::connection('tenant')
            ->table('loan_installments')
            ->selectRaw('loan_row_id')
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END) AS paid_count')
            ->selectRaw('SUM(CASE WHEN status <> \'paid\' THEN 1 ELSE 0 END) AS pending_count')
            ->selectRaw('MIN(CASE WHEN status <> \'paid\' THEN due_date ELSE NULL END) AS next_due_date')
            ->selectRaw('MAX(due_date) AS last_due_date')
            ->where('tenant_id', $tenantId)
            ->whereIn('loan_row_id', $loanRowIds)
            ->groupBy('loan_row_id')
            ->get();

        $statsByLoan = $installmentStats->keyBy('loan_row_id');

        // 3. Hitung total principal paid per loan (untuk sisa pokok).
        $principalPaidByLoan = DB::connection('tenant')
            ->table('loan_payment_allocations as a')
            ->join('loan_payments as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'a.tenant_id')
                    ->on('p.row_id', '=', 'a.payment_row_id');
            })
            ->where('a.tenant_id', $tenantId)
            ->whereIn('p.loan_row_id', $loanRowIds)
            ->where('a.component', 'principal')
            ->selectRaw('p.loan_row_id, SUM(a.amount) AS principal_paid')
            ->groupBy('p.loan_row_id')
            ->pluck('principal_paid', 'loan_row_id');

        // 4. Bangun baris-baris laporan.
        $rows = [];
        $totals = [
            'count' => 0,
            'principal_total' => 0.0,
            'remaining_principal_total' => 0.0,
            'remaining_tenor_sum' => 0,
        ];

        foreach ($loans as $loan) {
            $stats = $statsByLoan->get($loan->row_id);
            if ($stats === null) {
                // Tidak ada schedule → lewati (pinjaman tanpa installments).
                continue;
            }

            $totalCount = (int) ($stats->total_count ?? 0);
            $paidCount = (int) ($stats->paid_count ?? 0);
            $remainingTenor = max(0, $totalCount - $paidCount);

            // Wajib punya remaining_tenor <= MAX (1..3) untuk masuk laporan.
            if ($remainingTenor < 1 || $remainingTenor > self::MAX_REMAINING_TENOR) {
                continue;
            }

            $nextDueRaw = $stats->next_due_date ?? null;
            if ($nextDueRaw === null) {
                continue;
            }

            // next_due_date harus <= horizonEnd agar pinjaman benar-benar
            // akan jatuh tempo dalam horizon yang dipilih. Pengecualian:
            // jika as_of > next_due_date (sudah overdue) tetap masuk agar
            // tim collection melihat pinjaman yang terlambat.
            $nextDueDate = CarbonImmutable::parse((string) $nextDueRaw)->startOfDay();
            if ($nextDueDate->greaterThan($horizonEnd) && $nextDueDate->greaterThan($asOfDate)) {
                continue;
            }

            // Tgl estimasi lunas = due_date dari installment terakhir.
            $lastDueRaw = $stats->last_due_date ?? null;
            $estimatedSettlementDate = $lastDueRaw !== null
                ? CarbonImmutable::parse((string) $lastDueRaw)->startOfDay()
                : null;

            $principal = (float) $loan->principal_amount;
            $paidPrincipal = (float) ($principalPaidByLoan[$loan->row_id] ?? 0);
            $remainingPrincipal = max(0.0, round($principal - $paidPrincipal, 2));

            $installmentKe = $paidCount + 1;

            // Tentukan status jatuh tempo.
            $status = 'On Track';
            $statusTone = 'success';
            $daysToNext = $nextDueDate->diffInDays($asOfDate, false);
            if ($daysToNext > 0) {
                // next_due sudah lewat
                $status = 'Overdue';
                $statusTone = 'error';
            } elseif ($daysToNext === 0) {
                $status = 'Jatuh Tempo Hari Ini';
                $statusTone = 'warning';
            } elseif ($daysToNext >= -7) {
                // -7..-1 artinya due dalam 7 hari ke depan
                $status = 'Mendekati';
                $statusTone = 'warning';
            }

            $borrowerName = $loan->legacy_source === 'member_loan'
                ? ($loan->member_name ?? '—')
                : ($loan->group_name ?? '—');
            $borrowerIdentifier = $loan->legacy_source === 'member_loan'
                ? ($loan->member_number ?? $loan->nik ?? null)
                : ($loan->group_code ?? null);
            $villageName = $loan->legacy_source === 'member_loan'
                ? ($loan->member_village_name ?? null)
                : ($loan->group_village_name ?? null);

            $rows[] = [
                'loan_row_id' => (int) $loan->row_id,
                'loan_id' => (int) $loan->id,
                'loan_number' => (string) ($loan->loan_number ?? ('#'.$loan->id)),
                'product_code' => (string) ($loan->product_code ?? ''),
                'product_name' => (string) ($loan->product_name ?? ''),
                'borrower_kind' => $loan->legacy_source === 'member_loan' ? 'Individu' : 'Kelompok',
                'borrower_name' => (string) $borrowerName,
                'borrower_identifier' => $borrowerIdentifier,
                'village_name' => $villageName,
                'principal' => $principal,
                'remaining_principal' => $remainingPrincipal,
                'installment_number' => $installmentKe,
                'remaining_tenor' => $remainingTenor,
                'next_due_date' => $nextDueDate->toDateString(),
                'estimated_settlement_date' => $estimatedSettlementDate?->toDateString(),
                'days_to_next_due' => abs($daysToNext),
                'is_overdue' => $daysToNext > 0,
                'status' => $status,
                'status_tone' => $statusTone,
            ];

            $totals['count']++;
            $totals['principal_total'] += $principal;
            $totals['remaining_principal_total'] += $remainingPrincipal;
            $totals['remaining_tenor_sum'] += $remainingTenor;
        }

        // Sortir: yang paling dekat jatuh tempo di atas; overdue di atas on-track.
        usort($rows, function (array $a, array $b): int {
            // Overdue selalu di atas non-overdue
            if ($a['is_overdue'] !== $b['is_overdue']) {
                return $a['is_overdue'] ? -1 : 1;
            }

            // Lalu sort by next_due_date asc
            return strcmp($a['next_due_date'], $b['next_due_date']);
        });

        $totals['principal_total'] = round($totals['principal_total'], 2);
        $totals['remaining_principal_total'] = round($totals['remaining_principal_total'], 2);
        $totals['remaining_tenor_avg'] = $totals['count'] > 0
            ? round($totals['remaining_tenor_sum'] / $totals['count'], 1)
            : 0.0;

        return [
            'as_of' => $asOfDate->toDateString(),
            'horizon_end' => $horizonEnd->toDateString(),
            'horizon_days' => $horizonDays,
            'max_remaining_tenor' => self::MAX_REMAINING_TENOR,
            'borrower_scope' => $borrowerScope,
            'period_label' => sprintf(
                'Per Tanggal %s — %s ke Depan',
                $asOfDate->format('d/m/Y'),
                $horizonDays.' hari',
            ),
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
            ],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function resolveWeekRange(int $year, int $month, int $week): array
    {
        return WeeklyLppReportService::resolveWeekRange($year, $month, $week);
    }

    private function resolveAsOf(string $asOf): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) === 1) {
            try {
                return CarbonImmutable::parse($asOf)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::now()->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(
        CarbonImmutable $asOf,
        int $horizonDays,
        ?string $borrowerScope,
        $profile,
    ): array {
        $horizonEnd = $asOf->addDays($horizonDays);

        return [
            'as_of' => $asOf->toDateString(),
            'horizon_end' => $horizonEnd->toDateString(),
            'horizon_days' => $horizonDays,
            'max_remaining_tenor' => self::MAX_REMAINING_TENOR,
            'borrower_scope' => $borrowerScope,
            'period_label' => sprintf(
                'Per Tanggal %s — %s ke Depan',
                $asOf->format('d/m/Y'),
                $horizonDays.' hari',
            ),
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
            ],
            'rows' => [],
            'totals' => [
                'count' => 0,
                'principal_total' => 0.0,
                'remaining_principal_total' => 0.0,
                'remaining_tenor_sum' => 0,
                'remaining_tenor_avg' => 0.0,
            ],
        ];
    }
}
