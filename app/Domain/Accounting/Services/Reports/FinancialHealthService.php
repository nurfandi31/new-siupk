<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Lending\Services\Reports\CollectibilityReportService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;

/**
 * Penilaian Tingkat Kesehatan Keuangan BUMDesma / LKD (Permendesa / Kepmendesa No. 136/2022).
 * Menghitung rasio Kualitas Piutang, Likuiditas, Solvabilitas, Rentabilitas (ROA), BOPO, Skor & Predikat.
 */
final class FinancialHealthService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AccountBalanceQuery $balances,
        private readonly CollectibilityReportService $collectibility,
        private readonly IncomeStatementService $incomeStatement,
        private readonly BalanceSheetService $balanceSheet,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month = null): array
    {
        $month = $month ?? (int) date('n');
        $profile = OrganizationProfile::query()->first();

        // 1. Data Laba Rugi (Pendapatan & Biaya)
        $incomeData = $this->incomeStatement->build($year, $month);
        $pendapatan = (float) ($incomeData['totals']['revenue'] ?? 0);
        $biaya = (float) ($incomeData['totals']['expense'] ?? 0);
        $surplus = round($pendapatan - $biaya, 2);

        // 2. Data Neraca (Aset & Ekuitas)
        $balanceData = $this->balanceSheet->build($year, $month);
        $totalAset = (float) ($balanceData['totals']['assets'] ?? 0);
        $totalEkuitas = (float) ($balanceData['totals']['equity'] ?? 0);

        // 3. Data Kolektibilitas & CKPN Pinjaman
        $kolekData = $this->collectibility->buildCadangan($year, $month);
        $saldoPokok = (float) ($kolekData['totals']['saldo'] ?? 0);
        $tunggakanPokok = (float) ($kolekData['totals']['tunggakan_pokok'] ?? 0);
        $ckpn = (float) ($kolekData['totals']['total_ckpn'] ?? 0);
        $piutangMacet = (float) ($kolekData['totals']['kolek3_macet'] ?? 0);

        // 4. Perhitungan Rasio Finansial
        // Rasio 1: Piutang Berisiko (NPL) = Tunggakan Pokok / Saldo Pokok * 100
        $nplRatio = $saldoPokok > 0 ? round(($tunggakanPokok / $saldoPokok) * 100, 2) : 0.0;
        $skorNpl = 40.0;
        $statusNpl = 'Sehat';
        if ($nplRatio > 25) {
            $skorNpl = 5.0;
            $statusNpl = 'Tidak Sehat';
        } elseif ($nplRatio > 20) {
            $skorNpl = 10.0;
            $statusNpl = 'Kurang Sehat';
        } elseif ($nplRatio > 15) {
            $skorNpl = 20.0;
            $statusNpl = 'Kurang Sehat';
        } elseif ($nplRatio > 10) {
            $skorNpl = 30.0;
            $statusNpl = 'Cukup Sehat';
        } elseif ($nplRatio > 5) {
            $skorNpl = 35.0;
            $statusNpl = 'Sehat';
        }

        // Rasio 2: Kecukupan Cadangan Piutang (CKPN)
        $ckpnCoverage = $tunggakanPokok > 0 ? round(($ckpn / $tunggakanPokok) * 100, 2) : 100.0;
        $skorCkpn = 20.0;
        $statusCkpn = 'Sehat';
        if ($ckpnCoverage < 50) {
            $skorCkpn = 5.0;
            $statusCkpn = 'Tidak Sehat';
        } elseif ($ckpnCoverage < 75) {
            $skorCkpn = 10.0;
            $statusCkpn = 'Kurang Sehat';
        } elseif ($ckpnCoverage < 90) {
            $skorCkpn = 15.0;
            $statusCkpn = 'Cukup Sehat';
        }

        // Rasio 3: Rentabilitas Aset Produktif (ROA) = Surplus / Total Aset * 100
        $roaRatio = $totalAset > 0 ? round(($surplus / $totalAset) * 100, 2) : 0.0;
        $skorRoa = 15.0;
        $statusRoa = 'Sehat';
        if ($roaRatio < 2) {
            $skorRoa = 3.0;
            $statusRoa = 'Tidak Sehat';
        } elseif ($roaRatio < 5) {
            $skorRoa = 7.0;
            $statusRoa = 'Kurang Sehat';
        } elseif ($roaRatio < 8) {
            $skorRoa = 11.0;
            $statusRoa = 'Cukup Sehat';
        }

        // Rasio 4: Efisiensi Beban Operasional (BOPO) = Biaya / Pendapatan * 100
        $bopoRatio = $pendapatan > 0 ? round(($biaya / $pendapatan) * 100, 2) : 100.0;
        $skorBopo = 10.0;
        $statusBopo = 'Sehat';
        if ($bopoRatio > 95) {
            $skorBopo = 2.0;
            $statusBopo = 'Tidak Sehat';
        } elseif ($bopoRatio > 85) {
            $skorBopo = 5.0;
            $statusBopo = 'Kurang Sehat';
        } elseif ($bopoRatio > 75) {
            $skorBopo = 8.0;
            $statusBopo = 'Cukup Sehat';
        }

        // Rasio 5: Likuiditas (Piutang terhadap Total Aset)
        $likuiditasRatio = $totalAset > 0 ? round(($saldoPokok / $totalAset) * 100, 2) : 0.0;
        $skorLikuiditas = 5.0;
        $statusLikuiditas = 'Sehat';
        if ($likuiditasRatio < 40 || $likuiditasRatio > 95) {
            $skorLikuiditas = 2.0;
            $statusLikuiditas = 'Kurang Sehat';
        }

        // Rasio 6: Solvabilitas (Ekuitas Bersih terhadap Modal)
        $modalAwal = max(1.0, $totalEkuitas - $surplus);
        $solvabilitasRatio = round(($totalEkuitas / $modalAwal) * 100, 2);
        $skorSolvabilitas = 10.0;
        $statusSolvabilitas = 'Sehat';
        if ($solvabilitasRatio < 90) {
            $skorSolvabilitas = 2.0;
            $statusSolvabilitas = 'Tidak Sehat';
        } elseif ($solvabilitasRatio < 100) {
            $skorSolvabilitas = 6.0;
            $statusSolvabilitas = 'Cukup Sehat';
        }

        // Total Skor & Predikat
        $totalSkor = round($skorNpl + $skorCkpn + $skorRoa + $skorBopo + $skorLikuiditas + $skorSolvabilitas, 2);
        $predikat = 'SEHAT';
        $predikatClass = 'text-secondary bg-secondary-container border-secondary';
        if ($totalSkor < 51) {
            $predikat = 'TIDAK SEHAT';
            $predikatClass = 'text-on-error-container bg-error-container border-error';
        } elseif ($totalSkor < 66) {
            $predikat = 'KURANG SEHAT';
            $predikatClass = 'text-tertiary bg-tertiary-fixed border-tertiary';
        } elseif ($totalSkor < 80) {
            $predikat = 'CUKUP SEHAT';
            $predikatClass = 'text-tertiary bg-tertiary-fixed border-tertiary';
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
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
            ],
            'financial_data' => [
                'pendapatan' => $pendapatan,
                'biaya' => $biaya,
                'surplus' => $surplus,
                'total_aset' => $totalAset,
                'total_ekuitas' => $totalEkuitas,
                'saldo_pokok' => $saldoPokok,
                'tunggakan_pokok' => $tunggakanPokok,
                'ckpn' => $ckpn,
            ],
            'indicators' => [
                [
                    'name' => 'Kualitas Piutang (NPL Ratio)',
                    'desc' => 'Rasio tunggakan pokok terhadap total saldo pokok pinjaman aktif',
                    'formula' => 'Tunggakan Pokok / Saldo Pokok',
                    'value' => $nplRatio,
                    'unit' => '%',
                    'weight' => 40,
                    'score' => $skorNpl,
                    'status' => $statusNpl,
                ],
                [
                    'name' => 'Kecukupan Cadangan Piutang (CKPN)',
                    'desc' => 'Kecukupan alokasi cadangan penghapusan piutang terhadap tunggakan',
                    'formula' => 'Total CKPN / Tunggakan Pokok',
                    'value' => $ckpnCoverage,
                    'unit' => '%',
                    'weight' => 20,
                    'score' => $skorCkpn,
                    'status' => $statusCkpn,
                ],
                [
                    'name' => 'Rentabilitas Aset (ROA)',
                    'desc' => 'Tingkat pengembalian laba bersih terhadap total aset ekonomi',
                    'formula' => 'Surplus Bersih / Total Aset',
                    'value' => $roaRatio,
                    'unit' => '%',
                    'weight' => 15,
                    'score' => $skorRoa,
                    'status' => $statusRoa,
                ],
                [
                    'name' => 'Efisiensi Operasional (BOPO)',
                    'desc' => 'Rasio perbandingan beban biaya operasional terhadap total pendapatan',
                    'formula' => 'Total Biaya / Total Pendapatan',
                    'value' => $bopoRatio,
                    'unit' => '%',
                    'weight' => 10,
                    'score' => $skorBopo,
                    'status' => $statusBopo,
                ],
                [
                    'name' => 'Likuiditas Portofolio',
                    'desc' => 'Porsi saldo pinjaman beredar terhadap total komposisi aset',
                    'formula' => 'Saldo Pokok / Total Aset',
                    'value' => $likuiditasRatio,
                    'unit' => '%',
                    'weight' => 5,
                    'score' => $skorLikuiditas,
                    'status' => $statusLikuiditas,
                ],
                [
                    'name' => 'Solvabilitas Modal',
                    'desc' => 'Ketahanan ekuitas bersih terhadap penyertaan modal awal',
                    'formula' => 'Total Ekuitas / Modal Awal',
                    'value' => $solvabilitasRatio,
                    'unit' => '%',
                    'weight' => 10,
                    'score' => $skorSolvabilitas,
                    'status' => $statusSolvabilitas,
                ],
            ],
            'total_score' => $totalSkor,
            'predicate' => $predikat,
            'predicate_class' => $predikatClass,
        ];
    }
}
