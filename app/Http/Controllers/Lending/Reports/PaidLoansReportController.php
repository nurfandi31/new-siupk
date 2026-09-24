<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\PaidLoansReportService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaidLoansReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly PaidLoansReportService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $scope = $this->borrowerScope($request);

        return Inertia::render('Lending/Reports/PaidLoans', [
            ...$this->service->buildReport($year, $month, $scope),
            'filters' => ['year' => $year, 'month' => $month, 'scope' => $scope],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $scope = $this->borrowerScope($request);
        $data = $this->service->buildReport($year, $month, $scope);

        return $this->pdf->stream(
            'reports.pdf.lending.paid_loans',
            $data,
            sprintf('pinjaman-lunas-%04d-%02d.pdf', $year, $month),
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
}
