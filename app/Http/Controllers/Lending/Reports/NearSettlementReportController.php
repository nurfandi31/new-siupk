<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lending\Reports;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Lending\Services\Reports\NearSettlementReportService;
use App\Models\User;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pinjaman Mendekati Jatuh Tempo (Near Settlement).
 *
 * Filters:
 *   - as_of         : tanggal acuan (YYYY-MM-DD, default hari ini)
 *   - horizon_days  : berapa hari ke depan dari as_of (default 90)
 *   - scope         : all | group | member
 *
 * Routes:
 *   GET /lending/reports/near-settlement
 *   GET /lending/reports/near-settlement/pdf
 */
final class NearSettlementReportController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly NearSettlementReportService $service,
        private readonly ReportPdf $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        $asOf = $this->asOf($request);
        $horizonDays = $this->horizonDays($request);
        $scope = $this->scope($request);

        return Inertia::render('Lending/Reports/NearSettlement', [
            ...$this->service->buildReport($asOf, $scope, $horizonDays),
            'filters' => ['as_of' => $asOf, 'horizon_days' => $horizonDays, 'scope' => $scope],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        $asOf = $this->asOf($request);
        $horizonDays = $this->horizonDays($request);
        $scope = $this->scope($request);
        $data = $this->service->buildReport($asOf, $scope, $horizonDays);

        $scopeLabel = $scope === 'group' ? 'kelompok' : ($scope === 'member' ? 'individu' : 'semua');
        $filename = sprintf('pinjaman-mendekati-jatuh-tempo-%s-%s.pdf', $scopeLabel, $asOf);

        return $this->pdf->stream(
            'reports.pdf.lending.near_settlement',
            $data,
            $filename,
            'landscape',
        );
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'loans.view');
    }

    private function asOf(Request $request): string
    {
        $raw = (string) $request->query('as_of', date('Y-m-d'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return date('Y-m-d');
        }

        return $raw;
    }

    private function horizonDays(Request $request): int
    {
        $raw = (int) $request->query('horizon_days', NearSettlementReportService::DEFAULT_HORIZON_DAYS);
        if ($raw < 1 || $raw > 365) {
            return NearSettlementReportService::DEFAULT_HORIZON_DAYS;
        }

        return $raw;
    }

    private function scope(Request $request): ?string
    {
        $raw = (string) $request->query('scope', 'all');
        if (! in_array($raw, ['all', 'group', 'member'], true)) {
            return null;
        }

        return $raw === 'all' ? null : $raw;
    }
}
