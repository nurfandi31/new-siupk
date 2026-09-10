<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\CollectibilityReportService;
use App\Domain\Lending\Services\Reports\LoanPortfolioReportService;
use App\Domain\Lending\Services\Reports\LoanScheduleVsActualService;
use App\Domain\Lending\Services\Reports\LppReportService;
use App\Models\User;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LoanReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly LoanPortfolioReportService $portfolio,
        private readonly LoanScheduleVsActualService $scheduleVsActual,
        private readonly LppReportService $lpp,
        private readonly CollectibilityReportService $collectibility,
        private readonly ReportPdf $pdf,
    ) {}

    public function portfolio(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$asOf, $filter] = $this->filters($request);

        return Inertia::render('Lending/Reports/Portfolio', [
            ...$this->portfolio->build($asOf, $filter),
            'filters' => ['as_of' => $asOf, 'filter' => $filter],
        ]);
    }

    public function portfolioPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$asOf, $filter] = $this->filters($request);
        $data = $this->portfolio->build($asOf, $filter);

        return $this->pdf->stream(
            'reports.pdf.loan_portfolio',
            $data,
            'portofolio-pinjaman-'.$asOf.'.pdf',
            'landscape',
        );
    }

    public function scheduleVsActual(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);

        return Inertia::render('Lending/Reports/ScheduleVsActual', [
            ...$this->scheduleVsActual->build($year, $month),
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }

    public function scheduleVsActualPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $data = $this->scheduleVsActual->build($year, $month);

        return $this->pdf->stream(
            'reports.pdf.loan_schedule_vs_actual',
            $data,
            sprintf('rencana-realisasi-%04d-%02d.pdf', $year, $month),
            'landscape',
        );
    }

    public function lppDesa(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');

        return Inertia::render('Lending/Reports/LppDesa', [
            ...$this->lpp->buildDesa($year, $month, is_string($product) ? $product : null),
            'filters' => ['year' => $year, 'month' => $month, 'product' => $product],
        ]);
    }

    public function lppDesaPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');
        $data = $this->lpp->buildDesa($year, $month, is_string($product) ? $product : null);

        return $this->pdf->stream(
            'reports.pdf.lending.lpp_desa',
            $data,
            sprintf('lpp-desa-%04d-%02d.pdf', $year, $month),
            'landscape',
        );
    }

    public function lppKelompok(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');

        return Inertia::render('Lending/Reports/LppKelompok', [
            ...$this->lpp->buildKelompok($year, $month, is_string($product) ? $product : null),
            'filters' => ['year' => $year, 'month' => $month, 'product' => $product],
        ]);
    }

    public function lppKelompokPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');
        $data = $this->lpp->buildKelompok($year, $month, is_string($product) ? $product : null);

        return $this->pdf->stream(
            'reports.pdf.lending.lpp_kelompok',
            $data,
            sprintf('lpp-kelompok-%04d-%02d.pdf', $year, $month),
            'landscape',
        );
    }

    public function kolekDesa(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');

        return Inertia::render('Lending/Reports/KolekDesa', [
            ...$this->collectibility->buildDesa($year, $month, is_string($product) ? $product : null),
            'filters' => ['year' => $year, 'month' => $month, 'product' => $product],
        ]);
    }

    public function kolekDesaPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');
        $data = $this->collectibility->buildDesa($year, $month, is_string($product) ? $product : null);

        return $this->pdf->stream(
            'reports.pdf.lending.kolek_desa',
            $data,
            sprintf('kolektibilitas-desa-%04d-%02d.pdf', $year, $month),
            'landscape',
        );
    }

    public function cadanganPenghapusan(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');

        return Inertia::render('Lending/Reports/CadanganPenghapusan', [
            ...$this->collectibility->buildCadangan($year, $month, is_string($product) ? $product : null),
            'filters' => ['year' => $year, 'month' => $month, 'product' => $product],
        ]);
    }

    public function cadanganPenghapusanPdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');
        $data = $this->collectibility->buildCadangan($year, $month, is_string($product) ? $product : null);

        return $this->pdf->stream(
            'reports.pdf.lending.cadangan_penghapusan',
            $data,
            sprintf('cadangan-penghapusan-ckpn-%04d-%02d.pdf', $year, $month),
            'landscape',
        );
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'loans.view');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function filters(Request $request): array
    {
        $asOf = (string) $request->query('as_of', date('Y-m-d'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) !== 1) {
            $asOf = date('Y-m-d');
        }

        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'overdue', 'current'], true)) {
            $filter = 'all';
        }

        return [$asOf, $filter];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function yearMonth(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        return [$year, $month];
    }
}
