<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\OjkBalanceSheetService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Neraca OJK — Laporan Posisi Keuangan.
 *
 * Routes (defined in routes/web.php):
 *   GET  /regulatory/ojk/balance-sheet      → Inertia page
 *   GET  /regulatory/ojk/balance-sheet/pdf  → Portrait PDF stream
 *
 * Permission: `regulatory.view`.
 */
final class BalanceSheetController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly OjkBalanceSheetService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);

        return Inertia::render('Regulatory/Ojk/BalanceSheet', [
            ...$this->service->build($year, $month),
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $data = $this->service->build($year, $month);

        $suffix = $month !== null
            ? sprintf('%04d-%02d', $year, $month)
            : sprintf('%04d', $year);

        return $this->pdf->stream(
            'reports.pdf.ojk.balance_sheet',
            $data,
            'neraca-ojk-'.$suffix.'.pdf',
            'portrait',
        );
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'regulatory.view');
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function yearMonth(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $raw = $request->query('month', (string) date('n'));
        if ($raw === null || $raw === 'all' || $raw === '' || $raw === '0') {
            return [$year, null];
        }

        $month = (int) $raw;
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
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
