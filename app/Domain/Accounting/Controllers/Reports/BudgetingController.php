<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Controllers\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\Reports\BudgetingReportService;
use App\Models\User;
use App\Support\Excel\ReportExcel;
use App\Support\ReportPdf;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * E-Budgeting per Triwulan — Rencana vs Realisasi per Q1..Q4 (+ YTD).
 *
 * Routes (defined in routes/web.php):
 *   GET  /accounting/reports/budgeting         → Inertia page
 *   GET  /accounting/reports/budgeting/pdf     → Landscape PDF stream
 *   GET  /accounting/reports/budgeting/excel   → XLSX download
 *
 * Permission: `reports.view` — enforced via PermissionChecker.
 */
final class BudgetingController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly BudgetingReportService $service,
        private readonly ReportPdf $pdf,
        private readonly ReportExcel $excel,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $quarter, $ytd] = $this->resolvePeriod($request);
        $category = $this->resolveCategory($request);

        try {
            $payload = $this->service->build($year, $quarter, $category, $ytd);
        } catch (Throwable $e) {
            $payload = $this->emptyPayload($year, $quarter, $ytd, $category, $e->getMessage());
        }

        return Inertia::render('Accounting/Reports/Budgeting', [
            ...$payload,
            'filters' => [
                'year' => $year,
                'quarter' => $ytd ? 'ytd' : $quarter,
                'category' => $category ?? 'all',
            ],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $quarter, $ytd] = $this->resolvePeriod($request);
        $category = $this->resolveCategory($request);

        try {
            $data = $this->service->build($year, $quarter, $category, $ytd);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan E-Budgeting: '.$e->getMessage());
        }

        $suffix = $ytd
            ? sprintf('ytd-%04d', $year)
            : sprintf('%04d-q%d', $year, $quarter);

        return $this->pdf->stream(
            'reports.pdf.budgeting',
            $data,
            'e-budgeting-'.$suffix.'.pdf',
            'landscape',
        );
    }

    public function excel(Request $request): StreamedResponse
    {
        $this->authorize($request);
        [$year, $quarter, $ytd] = $this->resolvePeriod($request);
        $category = $this->resolveCategory($request);

        try {
            $data = $this->service->build($year, $quarter, $category, $ytd);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan E-Budgeting: '.$e->getMessage());
        }

        return $this->excel->budgeting($data);
    }

    /* ------------------------------------------------------------------ */
    /*                              Helpers */
    /* ------------------------------------------------------------------ */

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'reports.view');
    }

    /**
     * @return array{0:int,1:int,2:bool} [year, quarter, is_ytd]
     */
    private function resolvePeriod(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $raw = $request->query('quarter', 'q1');
        $ytd = false;
        $quarter = 1;

        if ($raw === null || $raw === '' || $raw === 'q1') {
            $quarter = 1;
        } elseif ($raw === 'q2') {
            $quarter = 2;
        } elseif ($raw === 'q3') {
            $quarter = 3;
        } elseif ($raw === 'q4') {
            $quarter = 4;
        } elseif ($raw === 'ytd' || $raw === 'all') {
            $ytd = true;
            $quarter = (int) ceil((int) date('n') / 3);
            if (! in_array($quarter, [1, 2, 3, 4], true)) {
                $quarter = 4;
            }
        } else {
            $quarter = (int) $raw;
            if ($quarter < 1 || $quarter > 4) {
                $quarter = (int) ceil((int) date('n') / 3);
                if (! in_array($quarter, [1, 2, 3, 4], true)) {
                    $quarter = 4;
                }
            }
        }

        return [$year, $quarter, $ytd];
    }

    private function resolveCategory(Request $request): ?string
    {
        $value = $request->query('category', 'all');
        if (! is_string($value) || $value === '' || $value === 'all') {
            return null;
        }

        return $value;
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyPayload(int $year, int $quarter, bool $ytd, ?string $category, ?string $error): array
    {
        return [
            'budget' => null,
            'period' => [
                'year' => $year,
                'quarter' => $quarter,
                'is_ytd' => $ytd,
                'label' => $ytd ? "YTD {$year}" : "Q{$quarter} {$year}",
                'range_label' => $ytd
                    ? "Januari – Desember {$year}"
                    : "Triwulan {$quarter} {$year}",
            ],
            'identity' => [
                'legal_name' => '',
                'short_name' => null,
            ],
            'category_filter' => $category,
            'categories' => [],
            'rows' => [],
            'totals' => [
                'revenue' => $this->emptyTotals(),
                'expense' => $this->emptyTotals(),
                'net' => $this->emptyTotals(),
            ],
            'generated_at' => null,
            'error' => $error,
        ];
    }

    /**
     * @return array{
     *   budget_quarter:float,realized_quarter:float,variance:float,realized_pct:float,
     *   budget_ytd:float,realized_ytd:float,variance_ytd:float,realized_pct_ytd:float
     * }
     */
    private function emptyTotals(): array
    {
        return [
            'budget_quarter' => 0.0,
            'realized_quarter' => 0.0,
            'variance' => 0.0,
            'realized_pct' => 0.0,
            'budget_ytd' => 0.0,
            'realized_ytd' => 0.0,
            'variance_ytd' => 0.0,
            'realized_pct_ytd' => 0.0,
        ];
    }
}
