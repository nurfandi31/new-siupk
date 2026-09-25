<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\ActiveBeneficiariesReportService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActiveBeneficiariesReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly ActiveBeneficiariesReportService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);

        return Inertia::render('Lending/Reports/ActiveBeneficiaries', [
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
            'reports.pdf.lending.active_beneficiaries',
            $data,
            sprintf('pemanfaat-aktif-kelompok-%04d-%02d.pdf', $year, $month),
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
}
