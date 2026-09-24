<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\SavingsReceivablesService;
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
 * SMPN — Simpanan & Piutang (OJK).
 *
 * Routes (defined in routes/web.php):
 *   GET  /regulatory/ojk/savings-receivables         → Inertia page
 *   GET  /regulatory/ojk/savings-receivables/pdf     → Portrait PDF stream
 *
 * Permission: `regulatory.view` — enforced via PermissionChecker,
 * matching the single-check-at-boundary pattern of other Domain
 * controllers (SimpananController, LoanReportController).
 */
final class SavingsReceivablesController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly SavingsReceivablesService $report,
        private readonly ReportPdf $pdf,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);

        try {
            $payload = $this->report->buildReport($this->tenantId(), $year, $month);
        } catch (Throwable $e) {
            $payload = $this->emptyPayload($year, $month, $e->getMessage());
        }

        return Inertia::render('Regulatory/Ojk/SavingsReceivables', [
            ...$payload,
            'monthLabels' => $this->monthLabels(),
            'filters' => ['year' => $year, 'month' => $month ?? 'all'],
        ]);
    }

    public function pdf(Request $request): Response|StreamedResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);

        try {
            $data = $this->report->buildReport($this->tenantId(), $year, $month);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan Simpanan & Piutang: '.$e->getMessage());
        }

        $suffix = $month !== null
            ? sprintf('%04d-%02d', $year, $month)
            : sprintf('%04d', $year);

        return $this->pdf->stream(
            'reports.pdf.ojk.savings_receivables',
            $data,
            'smpn-'.$suffix.'.pdf',
            'portrait',
        );
    }

    private function authorize(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        $this->permissions->denyUnless($user, 'regulatory.view');
    }

    private function tenantId(): int
    {
        return (int) ($this->tenantContext->id() ?? 0);
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function resolvePeriod(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $raw = $request->query('month', 'all');
        if ($raw === null || $raw === 'all' || $raw === '' || $raw === '0') {
            return [$year, null];
        }

        $month = (int) $raw;
        if ($month < 1 || $month > 12) {
            return [$year, null];
        }

        return [$year, $month];
    }

    /**
     * @return array<string, string>
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

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(int $year, ?int $month, string $error): array
    {
        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'as_of' => null,
                'from' => null,
                'until_exclusive' => null,
                'period_label' => $month !== null ? "Bulan {$month} {$year}" : "Tahun {$year}",
                'is_monthly' => $month !== null,
            ],
            'identity' => [],
            'passiva' => [
                'rows' => [],
                'by_kind' => [
                    'pokok' => ['label' => 'Simpanan Pokok', 'closing_balance' => 0.0, 'count' => 0],
                    'wajib' => ['label' => 'Simpanan Wajib', 'closing_balance' => 0.0, 'count' => 0],
                    'sukarela' => ['label' => 'Simpanan Sukarela', 'closing_balance' => 0.0, 'count' => 0],
                    'berjangka' => ['label' => 'Simpanan Berjangka', 'closing_balance' => 0.0, 'count' => 0],
                    'lainnya' => ['label' => 'Simpanan Lainnya', 'closing_balance' => 0.0, 'count' => 0],
                ],
                'total_closing' => 0.0,
            ],
            'aktiva' => [
                'rows' => [],
                'by_kind' => [
                    'pokok' => ['label' => 'Piutang Pokok', 'closing_balance' => 0.0, 'count' => 0],
                    'bunga' => ['label' => 'Piutang Bunga', 'closing_balance' => 0.0, 'count' => 0],
                    'lain' => ['label' => 'Piutang Lain', 'closing_balance' => 0.0, 'count' => 0],
                ],
                'pokok_closing' => 0.0,
                'bunga_closing' => 0.0,
                'lain_closing' => 0.0,
                'total_closing' => 0.0,
            ],
            'totals' => ['savings' => 0.0, 'receivables' => 0.0, 'selisih' => 0.0],
            'ratio' => ['piutang_to_simpanan' => 0.0, 'pokok_to_simpanan' => 0.0],
            'generated_at' => null,
            'tenant_id' => $this->tenantId(),
            'error' => $error,
        ];
    }
}
