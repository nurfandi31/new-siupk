<?php

declare(strict_types=1);

namespace App\Http\Controllers\Regency;

use App\Domain\Accounting\Services\Reports\RegencyConsolidatedReportService;
use App\Models\Platform\DatabaseShard;
use App\Services\RegencyGeoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RegencyDashboardController
{
    public function __construct(
        private readonly RegencyConsolidatedReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        /** @var DatabaseShard|null $shard */
        $shard = $request->attributes->get('regency_shard');
        if ($shard === null) {
            abort(500, 'Database shard untuk kabupaten belum terkonfigurasi.');
        }

        $user = $request->user();
        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null ? (int) $request->query('month') : (int) date('n');

        $kecamatans = $this->reportService->listKecamatans($shard, $user?->regency_code);
        $tenantIds = $kecamatans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $metrics = $this->reportService->dashboardMetrics($shard, $tenantIds, $year, $month);
        $regencyCode = $user?->regency_code ?: ($shard->regency_code ?: '');
        $regencyCenter = RegencyGeoService::resolveRegencyCenter($regencyCode);

        return Inertia::render('Regency/Dashboard', [
            'metrics' => $metrics,
            'year' => $year,
            'month' => $month,
            'regency_name' => $user?->regency_name ?: ($shard->regency_name ?: 'Kabupaten'),
            'regency_code' => $regencyCode,
            'regency_center' => $regencyCenter,
        ]);
    }
}
