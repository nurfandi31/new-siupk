<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Holding;

use App\Domain\Accounting\Services\Reports\BalanceSheetService;
use App\Domain\Accounting\Services\Reports\CalkService;
use App\Domain\Accounting\Services\Reports\CashFlowService;
use App\Domain\Accounting\Services\Reports\EquityChangeService;
use App\Domain\Accounting\Services\Reports\IncomeStatementService;
use App\Domain\Accounting\Services\Reports\ProvinceConsolidatedReportService;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class HoldingReportController
{
    public function __construct(
        private readonly BalanceSheetService $balanceSheetService,
        private readonly IncomeStatementService $incomeStatementService,
        private readonly CashFlowService $cashFlowService,
        private readonly EquityChangeService $equityChangeService,
        private readonly CalkService $calkService,
        private readonly ProvinceConsolidatedReportService $consolidatedReportService,
        private readonly TenantWorkbench $tenantWorkbench,
        private readonly ShardConnectionManager $shardConnectionManager,
    ) {}

    /**
     * Laporan Neraca (Balance Sheet).
     */
    public function balanceSheet(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null && $this->isConsolidatedRequest($request, $tenant)) {
            return $this->consolidatedBalanceSheet($request);
        }

        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        [$year, $month] = $this->parsePeriod($request);

        $data = $this->tenantWorkbench->run(
            $targetTenant,
            fn (): array => $this->balanceSheetService->build($year, $month),
        );

        return response()->json([
            'status' => 'success',
            'meta' => [
                'report' => 'balance_sheet',
                'report_title' => 'Laporan Neraca',
                'scope' => 'single_tenant',
                'tenant' => $this->tenantSummary($targetTenant),
                'period' => $data['period'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Laporan Laba Rugi (Income Statement / Profit & Loss).
     */
    public function incomeStatement(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null && $this->isConsolidatedRequest($request, $tenant)) {
            return $this->consolidatedIncomeStatement($request);
        }

        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        [$year, $month] = $this->parsePeriod($request);

        $data = $this->tenantWorkbench->run(
            $targetTenant,
            fn (): array => $this->incomeStatementService->build($year, $month),
        );

        return response()->json([
            'status' => 'success',
            'meta' => [
                'report' => 'income_statement',
                'report_title' => 'Laporan Laba Rugi',
                'scope' => 'single_tenant',
                'tenant' => $this->tenantSummary($targetTenant),
                'period' => $data['period'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Laporan Arus Kas (Cash Flow Statement).
     */
    public function cashFlow(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null && $this->isConsolidatedRequest($request, $tenant)) {
            return $this->consolidatedCashFlow($request);
        }

        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        [$year, $month] = $this->parsePeriod($request);

        $data = $this->tenantWorkbench->run(
            $targetTenant,
            fn (): array => $this->cashFlowService->build($year, $month),
        );

        return response()->json([
            'status' => 'success',
            'meta' => [
                'report' => 'cash_flow',
                'report_title' => 'Laporan Arus Kas',
                'scope' => 'single_tenant',
                'tenant' => $this->tenantSummary($targetTenant),
                'period' => $data['period'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Catatan Atas Laporan Keuangan (CALK).
     */
    public function calk(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null && $this->isConsolidatedRequest($request, $tenant)) {
            return $this->consolidatedCalk($request);
        }

        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        [$year, $month] = $this->parsePeriod($request);

        $data = $this->tenantWorkbench->run(
            $targetTenant,
            fn (): array => $this->calkService->build($year, $month),
        );

        return response()->json([
            'status' => 'success',
            'meta' => [
                'report' => 'calk',
                'report_title' => 'Catatan Atas Laporan Keuangan',
                'scope' => 'single_tenant',
                'tenant' => $this->tenantSummary($targetTenant),
                'period' => $data['period'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Laporan Perubahan Ekuitas / Modal (Statement of Changes in Equity).
     */
    public function equityChanges(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null && $this->isConsolidatedRequest($request, $tenant)) {
            return $this->consolidatedEquityChanges($request);
        }

        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        [$year, $month] = $this->parsePeriod($request);

        $data = $this->tenantWorkbench->run(
            $targetTenant,
            fn (): array => $this->equityChangeService->build($year, $month),
        );

        return response()->json([
            'status' => 'success',
            'meta' => [
                'report' => 'equity_changes',
                'report_title' => 'Laporan Perubahan Ekuitas',
                'scope' => 'single_tenant',
                'tenant' => $this->tenantSummary($targetTenant),
                'period' => $data['period'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Paket Lengkap Laporan Keuangan (5 Laporan Sekaligus).
     */
    public function pack(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null && $this->isConsolidatedRequest($request, $tenant)) {
            return $this->consolidatedPack($request);
        }

        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        [$year, $month] = $this->parsePeriod($request);

        $packData = $this->tenantWorkbench->run($targetTenant, fn (): array => [
            'balance_sheet' => $this->balanceSheetService->build($year, $month),
            'income_statement' => $this->incomeStatementService->build($year, $month),
            'cash_flow' => $this->cashFlowService->build($year, $month),
            'equity_changes' => $this->equityChangeService->build($year, $month),
            'calk' => $this->calkService->build($year, $month),
        ]);

        return response()->json([
            'status' => 'success',
            'meta' => [
                'report' => 'financial_report_pack',
                'report_title' => 'Paket Laporan Keuangan Lengkap',
                'scope' => 'single_tenant',
                'tenant' => $this->tenantSummary($targetTenant),
                'period' => $packData['balance_sheet']['period'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $packData,
        ]);
    }

    /**
     * Neraca Konsolidasi (Consolidated Balance Sheet).
     */
    public function consolidatedBalanceSheet(Request $request): JsonResponse
    {
        [$year, $month] = $this->parsePeriod($request);
        $tenants = $this->resolveConsolidatedTenants($request);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $shard = $tenants->first()?->placement?->shard ?? DatabaseShard::query()->where('status', 'active')->first();
        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
        }

        try {
            $data = $this->consolidatedReportService->balanceSheet($tenantIds, $year, $month);

            return response()->json([
                'status' => 'success',
                'meta' => [
                    'report' => 'balance_sheet',
                    'report_title' => 'Neraca Konsolidasi',
                    'scope' => 'consolidated',
                    'tenants_count' => count($tenantIds),
                    'period' => $data['period'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                ],
                'data' => $data,
            ]);
        } finally {
            if ($shard !== null) {
                $this->shardConnectionManager->disconnect();
            }
        }
    }

    /**
     * Laba Rugi Konsolidasi (Consolidated Income Statement).
     */
    public function consolidatedIncomeStatement(Request $request): JsonResponse
    {
        [$year, $month] = $this->parsePeriod($request);
        $tenants = $this->resolveConsolidatedTenants($request);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $shard = $tenants->first()?->placement?->shard ?? DatabaseShard::query()->where('status', 'active')->first();
        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
        }

        try {
            $data = $this->consolidatedReportService->incomeStatement($tenantIds, $year, $month);

            return response()->json([
                'status' => 'success',
                'meta' => [
                    'report' => 'income_statement',
                    'report_title' => 'Laba Rugi Konsolidasi',
                    'scope' => 'consolidated',
                    'tenants_count' => count($tenantIds),
                    'period' => $data['period'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                ],
                'data' => $data,
            ]);
        } finally {
            if ($shard !== null) {
                $this->shardConnectionManager->disconnect();
            }
        }
    }

    /**
     * Arus Kas Konsolidasi (Consolidated Cash Flow).
     */
    public function consolidatedCashFlow(Request $request): JsonResponse
    {
        [$year, $month] = $this->parsePeriod($request);
        $tenants = $this->resolveConsolidatedTenants($request);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $shard = $tenants->first()?->placement?->shard ?? DatabaseShard::query()->where('status', 'active')->first();
        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
        }

        try {
            $data = $this->consolidatedReportService->cashFlow($tenantIds, $year, $month);

            return response()->json([
                'status' => 'success',
                'meta' => [
                    'report' => 'cash_flow',
                    'report_title' => 'Arus Kas Konsolidasi',
                    'scope' => 'consolidated',
                    'tenants_count' => count($tenantIds),
                    'period' => $data['period'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                ],
                'data' => $data,
            ]);
        } finally {
            if ($shard !== null) {
                $this->shardConnectionManager->disconnect();
            }
        }
    }

    /**
     * Perubahan Ekuitas Konsolidasi (Consolidated Equity Changes).
     */
    public function consolidatedEquityChanges(Request $request): JsonResponse
    {
        [$year, $month] = $this->parsePeriod($request);
        $tenants = $this->resolveConsolidatedTenants($request);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $shard = $tenants->first()?->placement?->shard ?? DatabaseShard::query()->where('status', 'active')->first();
        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
        }

        try {
            $data = $this->consolidatedReportService->equityChanges($tenantIds, $year, $month);

            return response()->json([
                'status' => 'success',
                'meta' => [
                    'report' => 'equity_changes',
                    'report_title' => 'Perubahan Ekuitas Konsolidasi',
                    'scope' => 'consolidated',
                    'tenants_count' => count($tenantIds),
                    'period' => $data['period'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                ],
                'data' => $data,
            ]);
        } finally {
            if ($shard !== null) {
                $this->shardConnectionManager->disconnect();
            }
        }
    }

    /**
     * CALK Konsolidasi (Consolidated CALK).
     */
    public function consolidatedCalk(Request $request): JsonResponse
    {
        [$year, $month] = $this->parsePeriod($request);
        $tenants = $this->resolveConsolidatedTenants($request);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $shard = $tenants->first()?->placement?->shard ?? DatabaseShard::query()->where('status', 'active')->first();
        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
        }

        try {
            $data = $this->consolidatedReportService->calk($tenantIds, $year, $month);

            return response()->json([
                'status' => 'success',
                'meta' => [
                    'report' => 'calk',
                    'report_title' => 'Catatan Atas Laporan Keuangan Konsolidasi',
                    'scope' => 'consolidated',
                    'tenants_count' => count($tenantIds),
                    'period' => $data['period'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                ],
                'data' => $data,
            ]);
        } finally {
            if ($shard !== null) {
                $this->shardConnectionManager->disconnect();
            }
        }
    }

    /**
     * Paket Lengkap Konsolidasi (5 Laporan Sekaligus).
     */
    public function consolidatedPack(Request $request): JsonResponse
    {
        [$year, $month] = $this->parsePeriod($request);
        $tenants = $this->resolveConsolidatedTenants($request);
        $tenantIds = $tenants->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $shard = $tenants->first()?->placement?->shard ?? DatabaseShard::query()->where('status', 'active')->first();
        if ($shard !== null) {
            $this->shardConnectionManager->connect($shard);
        }

        try {
            $pack = $this->consolidatedReportService->combinedPack($tenantIds, $year, $month, 'Holding BUMDesma');

            return response()->json([
                'status' => 'success',
                'meta' => [
                    'report' => 'financial_report_pack',
                    'report_title' => 'Paket Laporan Keuangan Konsolidasi',
                    'scope' => 'consolidated',
                    'tenants_count' => count($tenantIds),
                    'period' => $pack['period'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                ],
                'data' => $pack,
            ]);
        } finally {
            if ($shard !== null) {
                $this->shardConnectionManager->disconnect();
            }
        }
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function parsePeriod(Request $request): array
    {
        $year = (int) $request->query('year', date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $rawMonth = $request->query('month');
        if ($rawMonth === null || $rawMonth === '' || $rawMonth === 'all' || $rawMonth === '0') {
            return [$year, null];
        }

        $month = (int) $rawMonth;
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        return [$year, $month];
    }

    private function resolveTenant(Request $request, ?string $routeTenant = null): ?Tenant
    {
        $raw = $routeTenant
            ?: $request->query('tenant')
            ?: $request->query('tenant_code')
            ?: $request->query('tenant_id')
            ?: $request->header('X-Tenant-Code')
            ?: $request->header('X-Tenant-Id');

        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $val = trim((string) $raw);
        if ($val === '' || strtolower($val) === 'all' || strtolower($val) === 'consolidated') {
            return null;
        }

        $query = Tenant::query()->with(['placement.shard'])->whereIn('status', ['active', 'read_only']);

        return is_numeric($val)
            ? $query->where('row_id', (int) $val)->first()
            : $query->where('code', $val)->first();
    }

    private function isConsolidatedRequest(Request $request, ?string $routeTenant = null): bool
    {
        $raw = $routeTenant ?: $request->query('tenant');
        if (is_string($raw) && (strtolower(trim($raw)) === 'all' || strtolower(trim($raw)) === 'consolidated')) {
            return true;
        }

        return $raw === null && ! $request->has('tenant_id') && ! $request->has('tenant_code') && ! $request->hasHeader('X-Tenant-Code');
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveConsolidatedTenants(Request $request): Collection
    {
        $query = Tenant::query()
            ->with(['placement.shard'])
            ->whereIn('status', ['active', 'read_only']);

        $tenantIds = $request->query('tenant_ids');
        if (is_array($tenantIds) && $tenantIds !== []) {
            $query->whereIn('row_id', array_map('intval', $tenantIds));
        } elseif (is_string($tenantIds) && trim($tenantIds) !== '') {
            $ids = array_filter(array_map('intval', explode(',', $tenantIds)));
            if ($ids !== []) {
                $query->whereIn('row_id', $ids);
            }
        }

        if ($request->filled('province_code')) {
            $query->where('province_code', (string) $request->query('province_code'));
        }

        if ($request->filled('regency_code')) {
            $query->where('regency_code', (string) $request->query('regency_code'));
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantSummary(Tenant $tenant): array
    {
        return [
            'id' => (int) $tenant->row_id,
            'code' => (string) $tenant->code,
            'name' => (string) $tenant->name,
            'district_code' => $tenant->district_code,
            'regency_code' => $tenant->regency_code,
            'regency_name' => $tenant->regency_name,
            'province_code' => $tenant->province_code,
        ];
    }

    private function tenantNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Holding subsidiary / tenant not found. Please provide a valid tenant code or ID.',
        ], 404);
    }
}
