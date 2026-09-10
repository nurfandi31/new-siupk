<?php

declare(strict_types=1);

namespace App\Http\Controllers\Regency;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\Reports\RegencyConsolidatedReportService;
use App\Models\Platform\DatabaseShard;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RegencyReportController
{
    public function __construct(
        private readonly RegencyConsolidatedReportService $reportService,
        private readonly ReportPdf $pdf,
    ) {}

    public function balanceSheet(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;

        $kecamatans = $this->reportService->listKecamatans($shard, $user?->regency_code);
        $tenantIds = $kecamatans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->balanceSheet($shard, $tenantIds, $year, $month, $tenantId);

        return Inertia::render('Regency/Reports/BalanceSheet', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'selected_tenant_id' => $tenantId,
            'regency_name' => $user?->regency_name ?: ($shard->regency_name ?: 'Kabupaten'),
        ]);
    }

    public function incomeStatement(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;

        $kecamatans = $this->reportService->listKecamatans($shard, $user?->regency_code);
        $tenantIds = $kecamatans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->incomeStatement($shard, $tenantIds, $year, $month, $tenantId);

        return Inertia::render('Regency/Reports/IncomeStatement', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'selected_tenant_id' => $tenantId,
            'regency_name' => $user?->regency_name ?: ($shard->regency_name ?: 'Kabupaten'),
        ]);
    }

    public function generalLedger(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;
        $day = $request->query('day') ? (string) $request->query('day') : null;

        $kecamatans = $this->reportService->listKecamatans($shard, $user?->regency_code);
        $tenantIds = $kecamatans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $defaultAccount = Account::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_postable', true)
            ->orderBy('code')
            ->first();

        $accountRowId = $request->query('account_id', $defaultAccount?->row_id ?? 1);

        $data = $this->reportService->generalLedger($shard, $tenantIds, $year, $month, $accountRowId, $tenantId, $day);

        return Inertia::render('Regency/Reports/GeneralLedger', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'selected_tenant_id' => $tenantId,
            'selected_account_id' => $accountRowId,
            'regency_name' => $user?->regency_name ?: ($shard->regency_name ?: 'Kabupaten'),
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;

        $kecamatans = $this->reportService->listKecamatans($shard, $user?->regency_code);
        $tenantIds = $kecamatans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->cashFlow($shard, $tenantIds, $year, $month, $tenantId);

        return Inertia::render('Regency/Reports/CashFlow', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'selected_tenant_id' => $tenantId,
            'regency_name' => $user?->regency_name ?: ($shard->regency_name ?: 'Kabupaten'),
        ]);
    }

    public function calk(Request $request): Response
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;

        $kecamatans = $this->reportService->listKecamatans($shard, $user?->regency_code);
        $tenantIds = $kecamatans->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $data = $this->reportService->calk($shard, $tenantIds, $year, $month, $tenantId);

        return Inertia::render('Regency/Reports/Calk', [
            'report' => $data,
            'year' => $year,
            'month' => $month,
            'selected_tenant_id' => $tenantId,
            'regency_name' => $user?->regency_name ?: ($shard->regency_name ?: 'Kabupaten'),
        ]);
    }

    public function pdf(Request $request, string $type): HttpResponse|StreamedResponse
    {
        $shard = $this->resolveShard($request);
        $user = $request->user();

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month') !== null && $request->query('month') !== '' ? (int) $request->query('month') : (int) date('n');
        $tenantId = $request->query('tenant_id') ? (int) $request->query('tenant_id') : null;

        $kecamatans = $this->reportService->listKecamatans($shard, $user?->regency_code);
        $tenantIds = $kecamatans->pluck('row_id')->map(fn ($id) => (int) $id)->all();
        $regencyName = (string) ($user?->regency_name ?: ($shard->regency_name ?: 'Kabupaten'));

        $orientation = 'portrait';
        $view = "reports.pdf.regency.{$type}";
        $filename = "laporan_{$type}_{$year}".($month ? "_{$month}" : '').'.pdf';

        switch ($type) {
            case 'balance-sheet':
                $data = $this->reportService->balanceSheet($shard, $tenantIds, $year, $month, $tenantId);
                $orientation = 'landscape';
                break;
            case 'income-statement':
                $data = $this->reportService->incomeStatement($shard, $tenantIds, $year, $month, $tenantId);
                $orientation = 'landscape';
                break;
            case 'general-ledger':
                $defaultAccount = Account::withoutGlobalScopes()
                    ->whereIn('tenant_id', $tenantIds)
                    ->where('is_postable', true)
                    ->orderBy('code')
                    ->first();
                $accountRowId = $request->query('account_id', $defaultAccount?->row_id ?? 1);
                $data = $this->reportService->generalLedger($shard, $tenantIds, $year, $month, $accountRowId, $tenantId);
                break;
            case 'cash-flow':
                $data = $this->reportService->cashFlow($shard, $tenantIds, $year, $month, $tenantId);
                break;
            case 'calk':
                $data = $this->reportService->calk($shard, $tenantIds, $year, $month, $tenantId);
                break;
            default:
                abort(404, 'Laporan tidak ditemukan.');
        }

        return $this->pdf->stream($view, [
            'report' => $data,
            'regency_name' => $regencyName,
            'year' => $year,
            'month' => $month,
        ], $filename, $orientation);
    }

    private function resolveShard(Request $request): DatabaseShard
    {
        /** @var DatabaseShard|null $shard */
        $shard = $request->attributes->get('regency_shard');
        if ($shard === null) {
            abort(500, 'Database shard untuk kabupaten belum terkonfigurasi.');
        }

        return $shard;
    }
}
