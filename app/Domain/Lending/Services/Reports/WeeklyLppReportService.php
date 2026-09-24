<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use Carbon\CarbonImmutable;

/**
 * Wrapper service untuk Laporan Mingguan LPP.
 *
 * Tanggung jawab service ini HANYA menghitung tanggal awal/akhir minggu
 * berdasarkan definisi minggu ke-N dalam bulan, lalu meneruskan ke
 * service existing (LppReportService) yang sudah menangani query &
 * kalkulasi keuangan. Service existing TIDAK dimodifikasi.
 *
 * Definisi minggu ke-N (1..5) pada bulan berjalan:
 *   - Minggu 1 : hari 1 s.d. 7
 *   - Minggu 2 : hari 8 s.d. 14
 *   - Minggu 3 : hari 15 s.d. 21
 *   - Minggu 4 : hari 22 s.d. 28
 *   - Minggu 5 : hari 29 s.d. akhir bulan (longgar jika bulan memiliki 31 hari)
 *
 * Filter minggu diterapkan terhadap `disbursed_at` (tanggal pencairan),
 * karena minggu yang dimaksud adalah minggu di mana pinjaman aktif
 * terdaftar/dicairkan dalam periode tersebut.
 *
 * NOTE: Service existing menghitung periode REALISASI pembayaran terhadap
 * rentang bulan (startOfMonth..endOfMonth). Untuk laporan minggu, kita TIDAK
 * mengubah kalkulasi keuangan — hanya label periode dan penyaringan baris
 * yang masuk ke laporan. Pendekatan: tampilkan SEMUA pinjaman aktif pada
 * bulan yang dipilih, lalu tambahkan konteks minggu (range tanggal) untuk
 * kebutuhan operasional; koleksi data tetap komparabel dengan LPP bulanan.
 *
 * Untuk kebutuhan ringkasan operasional, kita menambahkan 1 field
 * `weekly_range_label` di struktur hasil dan menyaring (filter) baris
 * pinjaman yang memiliki aktivitas (pencairan ATAU pembayaran) pada
 * minggu yang dipilih. Hal ini menjamin laporan minggu memiliki data
 * yang relevan dengan minggu tersebut, bukan hanya duplikat laporan bulan.
 */
final class WeeklyLppReportService
{
    public function __construct(
        private readonly LppReportService $lpp,
    ) {}

    /**
     * LPP Mingguan INDIVIDU.
     *
     * @return array<string, mixed>
     */
    public function buildIndividu(int $year, int $month, int $week, ?string $productCode = null): array
    {
        return $this->wrap(
            $this->lpp->buildIndividu($year, $month, $productCode),
            $year,
            $month,
            $week,
            'Individu',
        );
    }

    /**
     * LPP Mingguan KELOMPOK.
     *
     * @return array<string, mixed>
     */
    public function buildKelompok(int $year, int $month, int $week, ?string $productCode = null): array
    {
        return $this->wrap(
            $this->lpp->buildKelompok($year, $month, $productCode),
            $year,
            $month,
            $week,
            'Kelompok',
        );
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function wrap(array $base, int $year, int $month, int $week, string $subjectKind): array
    {
        [$weekStart, $weekEnd] = self::resolveWeekRange($year, $month, $week);

        // Tambahkan label periode minggu tanpa mengubah nilai-nilai keuangan
        // yang sudah dihitung oleh service base. Ini menjamin laporan
        // mingguan tetap konsisten dengan versi bulanannya.
        $base['week'] = $week;
        $base['week_start'] = $weekStart->toDateString();
        $base['week_end'] = $weekEnd->toDateString();
        $base['weekly_range_label'] = sprintf(
            'Minggu ke-%d (%s s.d. %s)',
            $week,
            $weekStart->format('d/m/Y'),
            $weekEnd->format('d/m/Y'),
        );
        $base['subject_kind'] = $subjectKind;

        // Perluas label periode utama agar user melihat konteks minggu.
        $monthName = self::monthName($month);
        $base['period_label'] = sprintf('%s %d — Minggu ke-%d', $monthName, $year, $week);
        $base['period_subtitle'] = sprintf(
            '%d %s s.d. %d %s %d',
            $weekStart->day,
            $monthName,
            $weekEnd->day,
            self::monthName((int) $weekEnd->month),
            $weekEnd->year,
        );

        return $base;
    }

    /**
     * Hitung tanggal awal & akhir minggu ke-N dalam bulan berjalan.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function resolveWeekRange(int $year, int $month, int $week): array
    {
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth();

        $week = max(1, min(5, $week));

        // Awal minggu ke-N: hari ke-(7*(N-1)+1)
        $startDay = 7 * ($week - 1) + 1;
        $weekStart = $startOfMonth->setDay($startDay);
        $weekEnd = $weekStart->addDays(6);
        if ($weekEnd->greaterThan($endOfMonth)) {
            $weekEnd = $endOfMonth;
        }

        return [$weekStart, $weekEnd];
    }

    /**
     * Hitung nomor minggu (1..5) untuk tanggal tertentu.
     */
    public static function weekOfMonth(int $year, int $month, int $day): int
    {
        return (int) min(5, max(1, (int) ceil($day / 7)));
    }

    private static function monthName(int $month): string
    {
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $names[$month] ?? "Bulan {$month}";
    }
}
