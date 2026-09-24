<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\AllowanceService;
use App\Models\User;
use App\Support\ReportPdf;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * PCPP — Penyisihan Cadangan Penghapusan Piutang (OJK).
 *
 * Routes (defined in routes/web.php):
 *   GET  /regulatory/ojk/allowance         → Inertia page
 *   GET  /regulatory/ojk/allowance/pdf     → Landscape PDF stream
 *
 * Permission: `regulatory.view`.
 */
final class AllowanceController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly AllowanceService $report,
        private readonly ReportPdf $pdf,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);
        $scope = $this->resolveScope($request);

        try {
            $payload = $this->report->buildReport($year, $month, $scope);
        } catch (Throwable $e) {
            $payload = $this->emptyPayload($year, $month, $scope, $e->getMessage());
        }

        return Inertia::render('Regulatory/Ojk/Allowance', [
            ...$payload,
            'filters' => ['year' => $year, 'month' => $month, 'scope' => $scope],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);
        $scope = $this->resolveScope($request);

        try {
            $data = $this->report->buildReport($year, $month, $scope);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan PCPP OJK: '.$e->getMessage());
        }

        return $this->pdf->stream(
            'reports.pdf.ojk.allowance',
            $data,
            sprintf('pcpp-ckpn-%04d-%02d.pdf', $year, $month),
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
    private function resolvePeriod(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        $month = (int) $request->query('month', date('n'));
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        return [$year, $month];
    }

    private function resolveScope(Request $request): string
    {
        $scope = (string) $request->query('scope', 'all');

        return in_array($scope, ['all', 'group', 'member'], true) ? $scope : 'all';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(int $year, int $month, string $scope, string $error): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'period_label' => '',
            'as_of' => null,
            'identity' => [],
            'borrower_scope' => $scope,
            'buckets' => [],
            'totals' => [
                'loan_count' => 0,
                'outstanding' => 0.0,
                'allowance_required' => 0.0,
                'allowance_formed' => 0.0,
                'selisih' => 0.0,
            ],
            'compliance_pct' => 0.0,
            'generated_at' => null,
            'tenant_id' => (int) ($this->tenantContext->id() ?? 0),
            'error' => $error,
        ];
    }
}
