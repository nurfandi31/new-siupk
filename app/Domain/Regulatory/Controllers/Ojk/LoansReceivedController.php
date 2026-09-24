<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\LoansReceivedService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DRPY — Rincian Pinjaman Diterima (OJK).
 *
 * Routes (defined in routes/web.php):
 *   GET  /regulatory/ojk/loans-received      → Inertia page
 *   GET  /regulatory/ojk/loans-received/pdf  → Landscape PDF stream
 *
 * Permission: `regulatory.view`.
 */
final class LoansReceivedController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly LoansReceivedService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $status = $this->status($request);
        $creditorType = $this->creditorType($request);

        return Inertia::render('Regulatory/Ojk/LoansReceived', [
            ...$this->service->buildReport($year, $month, $status, $creditorType),
            'filters' => [
                'year' => $year,
                'month' => $month,
                'status' => $status,
                'creditor_type' => $creditorType,
            ],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $status = $this->status($request);
        $creditorType = $this->creditorType($request);
        $data = $this->service->buildReport($year, $month, $status, $creditorType);

        return $this->pdf->stream(
            'reports.pdf.ojk.loans_received',
            $data,
            sprintf('drpy-pinjaman-diterima-%04d-%02d.pdf', $year, $month ?? 12),
            'landscape',
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

        $raw = $request->query('month');
        if ($raw === null || $raw === 'all' || $raw === '' || $raw === '0') {
            return [$year, null];
        }

        $month = (int) $raw;
        if ($month < 1 || $month > 12) {
            return [$year, null];
        }

        return [$year, $month];
    }

    private function status(Request $request): string
    {
        $s = (string) $request->query('status', 'all');
        if (! in_array($s, ['all', 'active', 'paid', 'written_off', 'restructured'], true)) {
            return 'all';
        }

        return $s;
    }

    private function creditorType(Request $request): string
    {
        $c = (string) $request->query('creditor_type', 'all');
        if (! in_array($c, ['all', 'bank', 'lembaga_keuangan', 'donor', 'pemerintah', 'lainnya'], true)) {
            return 'all';
        }

        return $c;
    }
}
