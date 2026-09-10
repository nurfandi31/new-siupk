<?php

declare(strict_types=1);

namespace App\Http\Controllers\Province;

use App\Domain\Accounting\Services\Reports\ProvinceConsolidatedReportService;
use App\Models\Platform\DatabaseShard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProvinceDashboardController
{
    public function __construct(
        private readonly ProvinceConsolidatedReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $metrics = $this->reportService->dashboardMetrics($tenantIds, $year, $month);

        return Inertia::render('Province/Dashboard', [
            'metrics' => $metrics,
            'year' => $year,
            'month' => $month,
            'province_name' => $user?->province_name ?: ($shard->province_name ?: 'Provinsi'),
        ]);
    }

    private function resolveShard(Request $request): DatabaseShard
    {
        /** @var DatabaseShard|null $shard */
        $shard = $request->attributes->get('province_shard');
        if ($shard === null) {
            abort(500, 'Database shard untuk provinsi belum terkonfigurasi.');
        }

        return $shard;
    }
}
