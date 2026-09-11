<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Desktop\DesktopSyncController;
use App\Http\Controllers\Api\Holding\HoldingReportController;
use App\Http\Controllers\Api\Holding\HoldingTenantController;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileCollectionController;
use App\Http\Controllers\Api\Mobile\MobileExecutiveController;
use App\Http\Controllers\Api\Mobile\MobileSyncController;
use App\Http\Controllers\Api\Mobile\MobileVerificationController;
use App\Http\Controllers\Assistant\AssistantToolController;
use Illuminate\Support\Facades\Route;

/*
| Tool callbacks from assistant orchestrator (server-to-server).
| Auth = HMAC signature + external_user_id -> platform user.
| Tenant resolved from host / optional X-Tenant-Code.
*/
Route::middleware(['orchestrator.signature', 'tenant', 'assistant.actor'])
    ->prefix('assistant/tools')
    ->group(function (): void {
        Route::post('/', AssistantToolController::class)->name('assistant.tools.dispatch');
        Route::post('/{tool}', AssistantToolController::class)
            ->where('tool', '[a-z0-9_]+')
            ->name('assistant.tools.run');
    });

/*
|--------------------------------------------------------------------------
| Holding Application Financial Reports API
|--------------------------------------------------------------------------
|
| Dedicated endpoints for holding / parent enterprise application to consume
| financial statements (Neraca, Laba Rugi, Arus Kas, CALK, Perubahan Ekuitas)
| per subsidiary tenant unit or consolidated.
|
*/
$registerHoldingRoutes = function (string $prefix): void {
    Route::middleware(['holding.auth'])
        ->prefix($prefix)
        ->group(function () use ($prefix): void {
            // Tenant / Subsidiary Directory
            Route::get('/tenants', [HoldingTenantController::class, 'index'])->name("api.{$prefix}.tenants.index");
            Route::get('/tenants/{tenant}', [HoldingTenantController::class, 'show'])->name("api.{$prefix}.tenants.show");

            // General / Query-based Financial Reports
            Route::prefix('reports')->group(function () use ($prefix): void {
                Route::get('/balance-sheet', [HoldingReportController::class, 'balanceSheet'])->name("api.{$prefix}.reports.balance-sheet");
                Route::get('/income-statement', [HoldingReportController::class, 'incomeStatement'])->name("api.{$prefix}.reports.income-statement");
                Route::get('/cash-flow', [HoldingReportController::class, 'cashFlow'])->name("api.{$prefix}.reports.cash-flow");
                Route::get('/calk', [HoldingReportController::class, 'calk'])->name("api.{$prefix}.reports.calk");
                Route::get('/equity-changes', [HoldingReportController::class, 'equityChanges'])->name("api.{$prefix}.reports.equity-changes");
                Route::get('/pack', [HoldingReportController::class, 'pack'])->name("api.{$prefix}.reports.pack");

                // Consolidated Reports
                Route::prefix('consolidated')->group(function () use ($prefix): void {
                    Route::get('/balance-sheet', [HoldingReportController::class, 'consolidatedBalanceSheet'])->name("api.{$prefix}.reports.consolidated.balance-sheet");
                    Route::get('/income-statement', [HoldingReportController::class, 'consolidatedIncomeStatement'])->name("api.{$prefix}.reports.consolidated.income-statement");
                    Route::get('/cash-flow', [HoldingReportController::class, 'consolidatedCashFlow'])->name("api.{$prefix}.reports.consolidated.cash-flow");
                    Route::get('/calk', [HoldingReportController::class, 'consolidatedCalk'])->name("api.{$prefix}.reports.consolidated.calk");
                    Route::get('/equity-changes', [HoldingReportController::class, 'consolidatedEquityChanges'])->name("api.{$prefix}.reports.consolidated.equity-changes");
                    Route::get('/pack', [HoldingReportController::class, 'consolidatedPack'])->name("api.{$prefix}.reports.consolidated.pack");
                });
            });

            // Tenant-scoped Financial Reports (/tenants/{tenant}/reports/...)
            Route::prefix('tenants/{tenant}/reports')->group(function () use ($prefix): void {
                Route::get('/balance-sheet', [HoldingReportController::class, 'balanceSheet'])->name("api.{$prefix}.tenants.reports.balance-sheet");
                Route::get('/income-statement', [HoldingReportController::class, 'incomeStatement'])->name("api.{$prefix}.tenants.reports.income-statement");
                Route::get('/cash-flow', [HoldingReportController::class, 'cashFlow'])->name("api.{$prefix}.tenants.reports.cash-flow");
                Route::get('/calk', [HoldingReportController::class, 'calk'])->name("api.{$prefix}.tenants.reports.calk");
                Route::get('/equity-changes', [HoldingReportController::class, 'equityChanges'])->name("api.{$prefix}.tenants.reports.equity-changes");
                Route::get('/pack', [HoldingReportController::class, 'pack'])->name("api.{$prefix}.tenants.reports.pack");
            });
        });
};

$registerHoldingRoutes('v1/holding');
$registerHoldingRoutes('holding');

/*
|--------------------------------------------------------------------------
| Desktop Client Synchronization API
|--------------------------------------------------------------------------
|
| Dedicated endpoints for the NativePHP desktop application to pull full/delta
| snapshots of a tenant database into local SQLite storage.
|
*/
$registerDesktopSyncRoutes = function (string $prefix): void {
    Route::middleware(['desktop.auth'])
        ->prefix($prefix)
        ->group(function () use ($prefix): void {
            Route::get('/status', [DesktopSyncController::class, 'status'])->name("api.{$prefix}.status");
            Route::get('/update/check', [DesktopSyncController::class, 'updateCheck'])->name("api.{$prefix}.update.check");
            Route::get('/snapshot', [DesktopSyncController::class, 'snapshot'])->name("api.{$prefix}.snapshot");
            Route::get('/delta', [DesktopSyncController::class, 'delta'])->name("api.{$prefix}.delta");

            Route::get('/tenants/{tenant}/status', [DesktopSyncController::class, 'status'])->name("api.{$prefix}.tenant.status");
            Route::get('/tenants/{tenant}/snapshot', [DesktopSyncController::class, 'snapshot'])->name("api.{$prefix}.tenant.snapshot");
            Route::get('/tenants/{tenant}/delta', [DesktopSyncController::class, 'delta'])->name("api.{$prefix}.tenant.delta");
            Route::post('/tenants/{tenant}/push', [DesktopSyncController::class, 'push'])
                ->name("api.{$prefix}.tenant.push");
        });
};

$registerDesktopSyncRoutes('v1/desktop/sync');
$registerDesktopSyncRoutes('desktop/sync');

/*
|--------------------------------------------------------------------------
| Mobile Companion Application API (Flutter)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/mobile')->name('api.v1.mobile.')->group(function (): void {
    // Public Auth
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->name('auth.login');

    // Authenticated Mobile
    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');

        // Collection & Field Installments
        Route::prefix('collection')->name('collection.')->group(function (): void {
            Route::get('/loans', [MobileCollectionController::class, 'index'])->name('loans.index');
            Route::get('/loans/{loan}', [MobileCollectionController::class, 'show'])->name('loans.show');
            Route::post('/loans/{loan}/pay', [MobileCollectionController::class, 'pay'])->name('loans.pay');
        });

        // Mobile offline synchronization
        Route::prefix('sync')->name('sync.')->group(function (): void {
            Route::get('/collection', [MobileSyncController::class, 'collection'])->name('collection');
            Route::post('/push', [MobileSyncController::class, 'push'])->name('push');
        });

        // Verification & Field Survey
        Route::prefix('verification')->name('verification.')->group(function (): void {
            Route::get('/proposals', [MobileVerificationController::class, 'index'])->name('proposals.index');
            Route::get('/proposals/{loan}', [MobileVerificationController::class, 'show'])->name('proposals.show');
            Route::post('/proposals/{loan}/verify', [MobileVerificationController::class, 'verify'])->name('proposals.verify');
        });

        // Executive & Approval
        Route::prefix('executive')->name('executive.')->group(function (): void {
            Route::get('/summary', [MobileExecutiveController::class, 'summary'])->name('summary');
            Route::get('/approvals', [MobileExecutiveController::class, 'approvals'])->name('approvals.index');
            Route::get('/approvals/{loan}', [MobileExecutiveController::class, 'showApproval'])->name('approvals.show');
            Route::post('/approvals/{loan}/approve', [MobileExecutiveController::class, 'approve'])->name('approvals.approve');
            Route::post('/approvals/{loan}/reject', [MobileExecutiveController::class, 'reject'])->name('approvals.reject');
        });
    });
});
