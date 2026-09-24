<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pinjaman Dihapusbukukan — INDIVIDU (legacy_source = 'member_loan').
 *
 * Menampilkan daftar pinjaman perorangan yang berstatus `written_off` dalam
 * periode tertentu (default: bulan berjalan). Identitas peminjam diambil dari
 * `members` + `people` (nama & NIK), desa dari `organization_units` anggota,
 * dan kelompok (jika ada) dari `loan_borrowers.group_row_id`.
 *
 * Sisa CKPN ditampilkan sebagai 100% dari sisa pokok saat write-off — konsisten
 * dengan kolektibilitas macet pada CollectibilityReportService::buildCadangan().
 */
final class WriteOffsIndividuReportService
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
    public function buildReport(int $year, int $month, ?string $productCode = null): array
    {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $profile = OrganizationProfile::query()->first();

        $loanQuery = DB::connection('tenant')
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
            ->leftJoin('organization_units as mv', function ($j): void {
                $j->on('mv.tenant_id', '=', 'm.tenant_id')
                    ->on('mv.row_id', '=', 'm.organization_unit_row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('loan_products as prod', function ($j): void {
                $j->on('prod.tenant_id', '=', 'l.tenant_id')
                    ->on('prod.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.status', 'written_off')
            ->where('l.legacy_source', 'member_loan');

        if ($productCode !== null && $productCode !== 'all') {
            $loanQuery->where('prod.code', $productCode);
        }

        $loans = $loanQuery
            ->orderBy('mv.name')
            ->orderBy('p.full_name')
            ->orderBy('l.id')
            ->get([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.principal_amount',
                'l.disbursed_at',
                'l.loan_product_row_id',
                'prod.code as product_code',
                'prod.name as product_name',
                'b.member_row_id',
                'b.group_row_id',
                'm.member_number',
                'p.full_name as member_name',
                'p.national_identity_number as nik',
                'mv.row_id as village_row_id',
                'mv.name as village_name',
                'g.code as group_code',
                'g.name as group_name',
            ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $writeOffs = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_write_offs')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $loanRowIds)
                ->orderBy('written_off_at')
                ->get([
                    'loan_row_id',
                    'principal_balance',
                    'interest_balance',
                    'written_off_at',
                    'reason',
                ]);

        $writeOffByLoan = $writeOffs->groupBy('loan_row_id')->map(function ($entries) {
            return $entries->sortByDesc('written_off_at')->first();
        });

        $realizedPokokByLoan = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_payment_allocations as a')
                ->join('loan_payments as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'a.tenant_id')
                        ->on('p.row_id', '=', 'a.payment_row_id');
                })
                ->where('a.tenant_id', $tenantId)
                ->whereIn('p.loan_row_id', $loanRowIds)
                ->where('a.component', 'principal')
                ->selectRaw('p.loan_row_id, sum(a.amount) as principal_paid')
                ->groupBy('p.loan_row_id')
                ->pluck('principal_paid', 'loan_row_id');

        $rows = [];
        $totals = [
            'count' => 0,
            'principal_total' => 0.0,
            'sisa_pokok_total' => 0.0,
            'ckpn_total' => 0.0,
            'nilai_bersih_total' => 0.0,
        ];

        foreach ($loans as $loan) {
            $wo = $writeOffByLoan->get($loan->row_id);
            $writtenOffAt = $wo?->written_off_at ? (string) $wo->written_off_at : null;

            $inPeriod = $writtenOffAt !== null
                && $writtenOffAt >= $startOfMonth
                && $writtenOffAt <= $endOfMonth.' 23:59:59';

            $principal = (float) $loan->principal_amount;
            $realized = (float) ($realizedPokokByLoan[$loan->row_id] ?? 0);
            $computedSaldo = max(0.0, round($principal - $realized, 2));

            $sisaPokok = $wo !== null
                ? round((float) ($wo->principal_balance ?? 0), 2)
                : $computedSaldo;

            $ckpn = round($sisaPokok * 1.00, 2);
            $nilaiBersih = round($sisaPokok - $ckpn, 2);

            $reason = $wo !== null ? (string) ($wo->reason ?? '') : '';
            $writtenOffAtLabel = $writtenOffAt !== null
                ? date('d/m/Y', strtotime(substr($writtenOffAt, 0, 10)))
                : '—';
            $writtenOffAtIso = $writtenOffAt !== null ? substr($writtenOffAt, 0, 10) : '';

            $rows[] = [
                'no' => count($rows) + 1,
                'loan_id' => (int) $loan->id,
                'loan_row_id' => (int) $loan->row_id,
                'loan_number' => (string) ($loan->loan_number ?? ''),
                'product_code' => (string) ($loan->product_code ?? ''),
                'product_name' => (string) ($loan->product_name ?? ''),
                'member_number' => (string) ($loan->member_number ?? ''),
                'member_name' => (string) ($loan->member_name ?? '—'),
                'nik' => $loan->nik,
                'village_name' => $loan->village_name,
                'group_code' => $loan->group_code,
                'group_name' => $loan->group_name,
                'principal_amount' => $principal,
                'sisa_pokok' => $sisaPokok,
                'ckpn' => $ckpn,
                'nilai_bersih' => $nilaiBersih,
                'written_off_at' => $writtenOffAt,
                'written_off_at_label' => $writtenOffAtLabel,
                'written_off_at_iso' => $writtenOffAtIso,
                'reason' => $reason,
                'in_period' => $inPeriod,
                'borrower_kind' => 'Individu',
            ];

            if ($inPeriod) {
                $totals['count']++;
                $totals['principal_total'] += $principal;
                $totals['sisa_pokok_total'] += $sisaPokok;
                $totals['ckpn_total'] += $ckpn;
                $totals['nilai_bersih_total'] += $nilaiBersih;
            }
        }

        $displayRows = array_values(array_filter($rows, fn ($r) => $r['in_period']));
        foreach ($displayRows as $i => &$r) {
            $r['no'] = $i + 1;
        }
        unset($r);

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
            'rows' => $displayRows,
            'totals' => [
                'count' => $totals['count'],
                'principal_total' => round($totals['principal_total'], 2),
                'sisa_pokok_total' => round($totals['sisa_pokok_total'], 2),
                'ckpn_total' => round($totals['ckpn_total'], 2),
                'nilai_bersih_total' => round($totals['nilai_bersih_total'], 2),
            ],
        ];
    }
}
