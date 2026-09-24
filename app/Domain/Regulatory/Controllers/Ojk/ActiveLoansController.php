<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\ActiveLoansService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DRP — Daftar Rincian Pinjaman Aktif (OJK).
 *
 * Routes (defined in routes/web.php):
 *   GET  /regulatory/ojk/active-loans        → Inertia page
 *   GET  /regulatory/ojk/active-loans/pdf    → Landscape PDF stream
 *
 * Permission: `regulatory.view`.
 */
final class ActiveLoansController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly ActiveLoansService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $scope = $this->borrowerScope($request);
        $collectibility = $this->collectibility($request);

        return Inertia::render('Regulatory/Ojk/ActiveLoans', [
            ...$this->service->buildReport($year, $month, $scope, $collectibility),
            'filters' => [
                'year' => $year,
                'month' => $month,
                'scope' => $scope ?? 'all',
                'collectibility' => $collectibility,
            ],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $scope = $this->borrowerScope($request);
        $collectibility = $this->collectibility($request);
        $data = $this->service->buildReport($year, $month, $scope, $collectibility);

        return $this->pdf->stream(
            'reports.pdf.ojk.active_loans',
            $data,
            sprintf('drp-pinjaman-aktif-%04d-%02d.pdf', $year, $month),
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

    private function borrowerScope(Request $request): ?string
    {
        $scope = (string) $request->query('scope', 'all');
        if (! in_array($scope, ['all', 'group', 'member'], true)) {
            $scope = 'all';
        }

        return $scope === 'all' ? null : $scope;
    }

    private function collectibility(Request $request): string
    {
        $c = (string) $request->query('collectibility', 'all');
        if (! in_array($c, ['all', 'current', 'overdue'], true)) {
            $c = 'all';
        }

        return $c;
    }
}
