<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\WriteOffBeneficiariesReportService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WriteOffBeneficiariesReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly WriteOffBeneficiariesReportService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');

        return Inertia::render('Lending/Reports/WriteOffBeneficiaries', [
            ...$this->service->buildReport($year, $month, is_string($product) ? $product : null),
            'filters' => ['year' => $year, 'month' => $month, 'product' => $product],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->yearMonth($request);
        $product = $request->query('product', 'all');
        $data = $this->service->buildReport($year, $month, is_string($product) ? $product : null);

        return $this->pdf->stream(
            'reports.pdf.lending.write_offs_beneficiaries',
            $data,
            sprintf('pinjaman-anggota-hapus-kelompok-%04d-%02d.pdf', $year, $month),
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
