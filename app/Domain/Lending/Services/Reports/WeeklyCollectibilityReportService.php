<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

/**
 * Wrapper service untuk Laporan Mingguan Kolektibilitas.
 *
 * - `buildIndividu`  : Reuse CollectibilityReportService::buildIndividu().
 * - `buildKelompok`  : Reuse CollectibilityReportService::buildDesa() — versi
 *                      kelompok memakai rekap desa (sama dengan kolek_desa
 *                      bulanan), sesuai pola yang sudah dipakai di
 *                      LoanReportController untuk laporan kolek per kelompok.
 *
 * Service existing tidak dimodifikasi — hanya menambahkan label periode
 * minggu dan metadata `weekly_range_label`.
 */
final class WeeklyCollectibilityReportService
{
    public function __construct(
        private readonly CollectibilityReportService $collectibility,
    ) {}

    /**
     * Kolek Mingguan INDIVIDU.
     *
     * @return array<string, mixed>
     */
    public function buildIndividu(int $year, int $month, int $week, ?string $productCode = null): array
    {
        return $this->wrap(
            $this->collectibility->buildIndividu($year, $month, $productCode),
            $year,
            $month,
            $week,
            'Individu',
        );
    }

    /**
     * Kolek Mingguan KELOMPOK — pakai rekap desa.
     *
     * @return array<string, mixed>
     */
    public function buildKelompok(int $year, int $month, int $week, ?string $productCode = null): array
    {
        // Laporan kolek untuk kelompok memakai rekap desa (sesuai pola
        // LoanReportController::kolekDesa()). Scope difilter ke 'group'
        // sehingga hanya pinjaman kelompok yang muncul.
        return $this->wrap(
            $this->collectibility->buildDesa($year, $month, $productCode, 'group'),
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
        [$weekStart, $weekEnd] = WeeklyLppReportService::resolveWeekRange($year, $month, $week);

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
