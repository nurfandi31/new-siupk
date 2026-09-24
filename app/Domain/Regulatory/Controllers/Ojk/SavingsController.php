<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Controllers\Ojk;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Regulatory\Services\SavingsService;
use App\Models\User;
use App\Support\ReportPdf as ReportPdfSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * DRT — Daftar Rincian Tabungan (OJK).
 *
 * Routes (defined in routes/web.php):
 *   GET  /regulatory/ojk/savings        → Inertia page
 *   GET  /regulatory/ojk/savings/pdf    → Landscape PDF stream
 *
 * Permission: `regulatory.view`.
 */
final class SavingsController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly SavingsService $service,
        private readonly ReportPdfSupport $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request);
        [$year, $month] = $this->resolvePeriod($request);

        try {
            $payload = $this->service->buildReport($year, $month);
        } catch (Throwable $e) {
            $payload = [
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
                'rows' => [],
                'by_kind' => [],
                'totals' => [
                    'opening_balance' => 0.0,
                    'period_debit' => 0.0,
                    'period_credit' => 0.0,
                    'closing_balance' => 0.0,
                ],
                'generated_at' => null,
                'tenant_id' => 0,
                'disclaimer' => 'Detail per anggota belum tersedia karena modul simpanan sedang dalam pengembangan.',
                'error' => $e->getMessage(),
            ];
        }

        return Inertia::render('Regulatory/Ojk/Savings', [
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
            $data = $this->service->buildReport($year, $month);
        } catch (Throwable $e) {
            abort(422, 'Gagal membangun laporan DRT: '.$e->getMessage());
        }

        $suffix = $month !== null
            ? sprintf('%04d-%02d', $year, $month)
            : sprintf('%04d', $year);

        return $this->pdf->stream(
            'reports.pdf.ojk.savings',
            $data,
            'drt-daftar-rincian-tabungan-'.$suffix.'.pdf',
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
     * @return array<int|string, string>
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
}
