<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pinjaman Dihapusbukukan — KELOMPOK (legacy_source = 'group_loan').
 *
 * Menampilkan daftar pinjaman kelompok yang berstatus `written_off`
 * (sesuai layanan write-off pada LoanService::writeOff) dalam periode
 * tertentu (default: bulan berjalan). Sisa CKPN yang ditampilkan adalah
 * saldo pokok saat pinjaman dihapusbukukan (`loan_write_offs.principal_balance`).
 *
 * Filter tambahan opsional: kode produk (`product`). Pencocokan tanggal
 * dilakukan pada kolom `loan_write_offs.written_off_at` — bila catatan
 * write-off tidak ada untuk sebuah loan berstatus `written_off`, baris
 * tetap dimunculkan dengan tanggal kosong (tidak di-exclude) untuk
 * menjaga kelengkapan audit trail.
 */
final class WriteOffsReportService
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
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->leftJoin('loan_products as prod', function ($j): void {
                $j->on('prod.tenant_id', '=', 'l.tenant_id')
                    ->on('prod.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.status', 'written_off')
            ->where(function ($w): void {
                $w->whereNull('l.legacy_source')
                    ->orWhere('l.legacy_source', 'group_loan');
            });

        if ($productCode !== null && $productCode !== 'all') {
            $loanQuery->where('prod.code', $productCode);
        }

        $loans = $loanQuery
            ->orderBy('v.name')
            ->orderBy('g.name')
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
                'g.row_id as group_row_id',
                'g.code as group_code',
                'g.name as group_name',
                'v.row_id as village_row_id',
                'v.name as village_name',
            ]);

        $loanRowIds = $loans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        // Catatan write-off per loan (LEFT JOIN: loan mungkin sudah written_off
        // tapi belum punya baris di loan_write_offs pada kasus hasil migrasi data).
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

        // Untuk tiap loan, gunakan entri write-off paling akhir (kalau ada > 1).
        $writeOffByLoan = $writeOffs->groupBy('loan_row_id')->map(function ($entries) {
            return $entries->sortByDesc('written_off_at')->first();
        });

        // Hitung saldo pokok aktual per loan (alokasi - realized_pokok),
        // sebagai fallback untuk `sisa_pokok` bila loan_write_offs tidak ada.
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

            // Filter periode hanya bila catatan write-off ada; jika tidak ada,
            // tetap dimunculkan (audit trail) tapi tidak dihitung pada totals
            // untuk menjaga konsistensi angka ringkasan periode.
            $inPeriod = $writtenOffAt !== null
                && $writtenOffAt >= $startOfMonth
                && $writtenOffAt <= $endOfMonth.' 23:59:59';

            $principal = (float) $loan->principal_amount;
            $realized = (float) ($realizedPokokByLoan[$loan->row_id] ?? 0);
            $computedSaldo = max(0.0, round($principal - $realized, 2));

            // Sisa pokok saat write-off: ambil dari loan_write_offs jika ada,
            // fallback ke saldo terhitung (alokasi - realized).
            $sisaPokok = $wo !== null
                ? round((float) ($wo->principal_balance ?? 0), 2)
                : $computedSaldo;

            // Cadangan CKPN yang sudah dibentuk sebelum hapus buku: diasumsikan
            // sama dengan sisa pokok untuk kategori macet (100%) — pola ini
            // konsisten dengan buildCadangan() pada CollectibilityReportService
            // untuk kolektibilitas macet. Jika catatan write-off tidak ada,
            // gunakan computed saldo sebagai basis perhitungan CKPN.
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
                'group_code' => (string) ($loan->group_code ?? ''),
                'group_name' => (string) ($loan->group_name ?? ''),
                'village_name' => $loan->village_name,
                'principal_amount' => $principal,
                'sisa_pokok' => $sisaPokok,
                'ckpn' => $ckpn,
                'nilai_bersih' => $nilaiBersih,
                'written_off_at' => $writtenOffAt,
                'written_off_at_label' => $writtenOffAtLabel,
                'written_off_at_iso' => $writtenOffAtIso,
                'reason' => $reason,
                'in_period' => $inPeriod,
                'borrower_kind' => 'Kelompok',
            ];

            // Total agregat hanya menghitung baris dalam periode.
            if ($inPeriod) {
                $totals['count']++;
                $totals['principal_total'] += $principal;
                $totals['sisa_pokok_total'] += $sisaPokok;
                $totals['ckpn_total'] += $ckpn;
                $totals['nilai_bersih_total'] += $nilaiBersih;
            }
        }

        // Tampilkan hanya baris yang ada di periode (baris di luar periode
        // disembunyikan dari laporan utama untuk menjaga kejelasan ringkasan).
        $displayRows = array_values(array_filter($rows, fn ($r) => $r['in_period']));

        // Re-nomor setelah filter agar konsisten dengan totals.
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
