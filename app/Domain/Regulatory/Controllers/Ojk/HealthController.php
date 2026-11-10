<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\OjkHealthService;
use App\Models\User;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Penilaian Tingkat Kesehatan LKM (OJK).
 *
 * Routes:
 *   GET /regulatory/ojk/health      -> Inertia page
 *   GET /regulatory/ojk/health/pdf  -> PDF
 *
 * Query: year, month, framework=19|41
 * Permission: regulatory.view
 */
final class HealthController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly OjkHealthService $service,
        private readonly ReportPdf $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);
        $framework = $this->resolveFramework($request);

        try {
            $payload = $this->service->build($year, $month, $framework);
        } catch (Throwable $e) {
            $payload = $this->emptyPayload($year, $month, $framework, $e->getMessage());
        }

        return Inertia::render('Regulatory/Ojk/HealthAssessment', [
            ...$payload,
            'filters' => ['year' => $year, 'month' => $month, 'framework' => $framework],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);
        $framework = $this->resolveFramework($request);

        try {
            $data = $this->service->build($year, $month, $framework);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan penilaian kesehatan OJK: '.$e->getMessage());
        }

        return $this->pdf->stream(
            'reports.pdf.ojk.health_assessment',
            $data,
            sprintf('penilaian-kesehatan-ojk-pojk%s-%04d-%02d.pdf', $framework, $year, $month),
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

    private function resolveFramework(Request $request): string
    {
        return (string) $request->query('framework', '41') === '19' ? '19' : '41';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(int $year, int $month, string $framework, string $error): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'framework' => $framework,
            'framework_label' => $framework === '19' ? 'POJK NO. 19/POJK.05/2021' : 'POJK NO. 41/POJK.05/2024 & POJK NO. 49/POJK.05/2024',
            'title' => $framework === '19' ? 'PENILAIAN KESEHATAN OJK' : 'PENILAIAN TINGKAT KESEHATAN LKM',
            'period_label' => '',
            'as_of' => null,
            'identity' => [],
            'parameters' => [],
            'values' => [],
            'detail_kolek' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'rows' => [],
            'composite_score' => 0,
            'composite_max' => 100,
            'rating' => '-',
            'supervision_status' => null,
            'supervision_basis' => null,
            'summary_label' => 'SKOR',
            'generated_at' => null,
            'filters' => ['year' => $year, 'month' => $month, 'framework' => $framework],
            'error' => $error,
        ];
    }
}
