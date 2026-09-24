<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\PaidGroupLoansService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DRPL — Rincian Pinjaman Lunas Kelompok (OJK).
 *
 * Routes (defined in routes/web.php):
 *   GET  /regulatory/ojk/paid-group         → Inertia page
 *   GET  /regulatory/ojk/paid-group/pdf     → Landscape PDF stream
 *
 * Permission: `regulatory.view`.
 */
final class PaidGroupLoansController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly PaidGroupLoansService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);

        return Inertia::render('Regulatory/Ojk/PaidGroup', [
            ...$this->service->buildReport($year, $month),
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $data = $this->service->buildReport($year, $month);

        return $this->pdf->stream(
            'reports.pdf.ojk.paid_group',
            $data,
            sprintf('drpl-pinjaman-lunas-kelompok-%04d-%02d.pdf', $year, $month),
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
