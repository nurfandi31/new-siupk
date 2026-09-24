<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\WeeklyCollectibilityReportService;
use App\Domain\Lending\Services\Reports\WeeklyLppReportService;
use App\Models\User;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller untuk 4 laporan mingguan:
 *   1. LPP Mingguan Individu
 *   2. LPP Mingguan Kelompok
 *   3. Kolek Mingguan Individu
 *   4. Kolek Mingguan Kelompok (rekap desa)
 *
 * Routes:
 *   GET /lending/reports/weekly/lpp-individu
 *   GET /lending/reports/weekly/lpp-individu/pdf
 *   GET /lending/reports/weekly/lpp-kelompok
 *   GET /lending/reports/weekly/lpp-kelompok/pdf
 *   GET /lending/reports/weekly/kolek-individu
 *   GET /lending/reports/weekly/kolek-individu/pdf
 *   GET /lending/reports/weekly/kolek-kelompok
 *   GET /lending/reports/weekly/kolek-kelompok/pdf
 */
final class WeeklyReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly WeeklyLppReportService $weeklyLpp,
        private readonly WeeklyCollectibilityReportService $weeklyCollectibility,
        private readonly ReportPdf $pdf,
    ) {}

    /* ---------- LPP Mingguan Individu ---------- */

    public function lppIndividu(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);

        return Inertia::render('Lending/Reports/Weekly/LppIndividu', [
            ...$this->weeklyLpp->buildIndividu($year, $month, $week, $product),
            'filters' => ['year' => $year, 'month' => $month, 'week' => $week, 'product' => $product],
        ]);
    }

    public function lppIndividuPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);
        $data = $this->weeklyLpp->buildIndividu($year, $month, $week, $product);

        return $this->pdf->stream(
            'reports.pdf.lending.weekly_lpp_individu',
            $data,
            sprintf('lpp-mingguan-individu-%04d-%02d-w%d.pdf', $year, $month, $week),
            'landscape',
        );
    }

    /* ---------- LPP Mingguan Kelompok ---------- */

    public function lppKelompok(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);

        return Inertia::render('Lending/Reports/Weekly/LppKelompok', [
            ...$this->weeklyLpp->buildKelompok($year, $month, $week, $product),
            'filters' => ['year' => $year, 'month' => $month, 'week' => $week, 'product' => $product],
        ]);
    }

    public function lppKelompokPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);
        $data = $this->weeklyLpp->buildKelompok($year, $month, $week, $product);

        return $this->pdf->stream(
            'reports.pdf.lending.weekly_lpp_kelompok',
            $data,
            sprintf('lpp-mingguan-kelompok-%04d-%02d-w%d.pdf', $year, $month, $week),
            'landscape',
        );
    }

    /* ---------- Kolek Mingguan Individu ---------- */

    public function kolekIndividu(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);

        return Inertia::render('Lending/Reports/Weekly/KolekIndividu', [
            ...$this->weeklyCollectibility->buildIndividu($year, $month, $week, $product),
            'filters' => ['year' => $year, 'month' => $month, 'week' => $week, 'product' => $product],
        ]);
    }

    public function kolekIndividuPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);
        $data = $this->weeklyCollectibility->buildIndividu($year, $month, $week, $product);

        return $this->pdf->stream(
            'reports.pdf.lending.weekly_kolek_individu',
            $data,
            sprintf('kolek-mingguan-individu-%04d-%02d-w%d.pdf', $year, $month, $week),
            'landscape',
        );
    }

    /* ---------- Kolek Mingguan Kelompok (Rekap Desa) ---------- */

    public function kolekKelompok(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);

        return Inertia::render('Lending/Reports/Weekly/KolekKelompok', [
            ...$this->weeklyCollectibility->buildKelompok($year, $month, $week, $product),
            'filters' => ['year' => $year, 'month' => $month, 'week' => $week, 'product' => $product],
        ]);
    }

    public function kolekKelompokPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month, $week] = $this->yearMonthWeek($request);
        $product = $this->productCode($request);
        $data = $this->weeklyCollectibility->buildKelompok($year, $month, $week, $product);

        return $this->pdf->stream(
            'reports.pdf.lending.weekly_kolek_kelompok',
            $data,
            sprintf('kolek-mingguan-kelompok-%04d-%02d-w%d.pdf', $year, $month, $week),
            'landscape',
        );
    }

    /* ---------- helpers ---------- */

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'loans.view');
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function yearMonthWeek(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));
        $week = (int) $request->query('week', 1);

        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($week < 1 || $week > 5) {
            $week = 1;
        }

        return [$year, $month, $week];
    }

    private function productCode(Request $request): ?string
    {
        $product = $request->query('product', 'all');
        if (! is_string($product) || $product === '' || $product === 'all') {
            return null;
        }

        return $product;
    }
}
