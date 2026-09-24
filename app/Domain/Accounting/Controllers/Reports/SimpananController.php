<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Controllers\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\Reports\SimpananReportService;
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
 * Daftar Simpanan — savings register grouped by simpanan kind
 * (Pokok / Wajib / Sukarela / Berjangka).
 *
 * Routes (defined in routes/web.php):
 *   GET  /accounting/reports/simpanan         → Inertia page
 *   GET  /accounting/reports/simpanan/pdf     → Landscape PDF stream
 *   GET  /accounting/reports/simpanan/excel   → XLSX download
 *
 * Permission: `reports.view` — enforced via PermissionChecker, matching the
 * existing ReportController pattern (single-check at the controller boundary,
 * not middleware).
 */
final class SimpananController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly SimpananReportService $report,
        private readonly ReportPdf $pdf,
        private readonly ReportExcel $excel,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);

        try {
            $payload = $this->report->buildReport($this->tenantId(), $year, $month);
        } catch (Throwable $e) {
            // Defensive fallback — show empty report so the user can still
            // change filters / re-try without breaking the page render.
            $payload = [
                'period' => [
                    'year' => $year,
                    'month' => $month,
                    'as_of' => null,
                    'from' => null,
                    'until_exclusive' => null,
                    'period_label' => $month !== null ? "Bulan {$month} {$year}" : "Tahun {$year}",
                    'is_monthly' => $month !== null,
                ],
                'identity' => [],
                'rows' => [],
                'by_kind' => [],
                'totals' => [
                    'opening_balance' => 0.0,
                    'period_debit' => 0.0,
                    'period_credit' => 0.0,
                    'closing_balance' => 0.0,
                ],
                'generated_at' => null,
                'tenant_id' => $this->tenantId(),
                'error' => $e->getMessage(),
            ];
        }

        return Inertia::render('Accounting/Reports/Simpanan', [
            ...$payload,
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);

        try {
            $data = $this->report->buildReport($this->tenantId(), $year, $month);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan Daftar Simpanan: '.$e->getMessage());
        }

        $suffix = $month !== null
            ? sprintf('%04d-%02d', $year, $month)
            : sprintf('%04d', $year);

        return $this->pdf->stream(
            'reports.pdf.simpanan',
            $data,
            'daftar-simpanan-'.$suffix.'.pdf',
            'landscape',
        );
    }

    public function excel(Request $request): StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);

        try {
            $data = $this->report->buildReport($this->tenantId(), $year, $month);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan Daftar Simpanan: '.$e->getMessage());
        }

        return $this->excel->simpanan($data);
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'reports.view');
    }

    private function tenantId(): int
    {
        return (int) ($this->tenantContext->id() ?? 0);
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function resolvePeriod(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $raw = $request->query('month', 'all');
        if ($raw === null || $raw === 'all' || $raw === '' || $raw === '0') {
            return [$year, null];
        }

        $month = (int) $raw;
        if ($month < 1 || $month > 12) {
            return [$year, null];
        }

        return [$year, $month];
    }

    /**
     * @return array<int|string, string>
     */
    private function monthLabels(): array
    {
        return [
            'all' => 'Januari – Desember',
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }
}
