<?php

declare(strict_types=1);

namespace App\Http\Controllers\Province;

use App\Domain\Accounting\Services\Reports\ProvinceConsolidatedReportService;
use App\Models\Platform\DatabaseShard;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProvinceReportController
{
    public function __construct(
        private readonly ProvinceConsolidatedReportService $reportService,
        private readonly ReportPdf $pdf,
    ) {}

    public function pack(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        $provinceName = (string) ($user?->province_name ?: ($shard->province_name ?: 'Provinsi'));

        $pack = $this->reportService->combinedPack($tenantIds, $year, $month, $provinceName);

        return Inertia::render('Province/Reports/Pack', [
            'pack' => $pack,
            'year' => $year,
            'month' => $month,
            'province_name' => $provinceName,
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->balanceSheet($tenantIds, $year, $month);

        return Inertia::render('Province/Reports/BalanceSheet', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'province_name' => $user?->province_name ?: ($shard->province_name ?: 'Provinsi'),
        ]);
    }

    public function incomeStatement(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->incomeStatement($tenantIds, $year, $month);

        return Inertia::render('Province/Reports/IncomeStatement', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'province_name' => $user?->province_name ?: ($shard->province_name ?: 'Provinsi'),
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->cashFlow($tenantIds, $year, $month);

        return Inertia::render('Province/Reports/CashFlow', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'province_name' => $user?->province_name ?: ($shard->province_name ?: 'Provinsi'),
        ]);
    }

    public function equityChanges(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->equityChanges($tenantIds, $year, $month);

        return Inertia::render('Province/Reports/EquityChanges', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'province_name' => $user?->province_name ?: ($shard->province_name ?: 'Provinsi'),
        ]);
    }

    public function calk(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->calk($tenantIds, $year, $month);

        return Inertia::render('Province/Reports/Calk', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'province_name' => $user?->province_name ?: ($shard->province_name ?: 'Provinsi'),
        ]);
    }

    public function pdf(Request $request): HttpResponse|StreamedResponse
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');

        $tenants = $this->reportService->listTenants($user?->province_code);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        $provinceName = (string) ($user?->province_name ?: ($shard->province_name ?: 'Provinsi'));

        $pack = $this->reportService->combinedPack($tenantIds, $year, $month, $provinceName);
        $filename = "laporan_keuangan_provinsi_{$year}".($month ? "_{$month}" : '').'.pdf';

        return $this->pdf->stream('reports.pdf.province.pack', [
            'pack' => $pack,
            'province_name' => $provinceName,
            'year' => $year,
            'month' => $month,
        ], $filename, 'portrait');
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
