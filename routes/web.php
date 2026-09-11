<?php

declare(strict_types=1);

use App\Http\Controllers\Access\TenantRoleManagementController;
use App\Http\Controllers\Access\TenantUserManagementController;
use App\Http\Controllers\Accounting\CashEvidenceController;
use App\Http\Controllers\Accounting\ChartOfAccountsController;
use App\Http\Controllers\Accounting\JournalBrowseController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\PeriodCloseController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\TaxEstimateController;
use App\Http\Controllers\Admin\AiAssistantController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\InvoicePaymentController as AdminInvoicePaymentController;
use App\Http\Controllers\Admin\MigrationController as AdminMigrationController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\PlatformWhatsappController;
use App\Http\Controllers\Admin\RevenueController as AdminRevenueController;
use App\Http\Controllers\Admin\ShardController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\TenantDataPurifierController;
use App\Http\Controllers\Admin\TenantImpersonationController as AdminTenantImpersonationController;
use App\Http\Controllers\Admin\TenantUserController as AdminTenantUserController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Billing\InvoiceController as TenantInvoiceController;
use App\Http\Controllers\Budgeting\BudgetController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesktopClientController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Lending\LoanController;
use App\Http\Controllers\Lending\LoanDocumentController;
use App\Http\Controllers\Lending\LoanReportController;
use App\Http\Controllers\Lending\LoanSimulationController;
use App\Http\Controllers\MasterData\GroupController;
use App\Http\Controllers\MasterData\MemberController;
use App\Http\Controllers\MasterData\OtherInstitutionController;
use App\Http\Controllers\MasterData\VillageController;
use App\Http\Controllers\Notifications\BillingNoticeController;
use App\Http\Controllers\Notifications\NotificationCenterController;
use App\Http\Controllers\Portal\PortalMemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Province\ProvinceDashboardController;
use App\Http\Controllers\Province\ProvinceReportController;
use App\Http\Controllers\PublicSite\PublicSiteController;
use App\Http\Controllers\Regency\RegencyDashboardController;
use App\Http\Controllers\Regency\RegencyReportController;
use App\Http\Controllers\RegionalCodeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\WhatsappSettingController;
use App\Http\Controllers\StorageServeController;
use App\Http\Controllers\Tenant\TenantOnboardingImportController;
use App\Http\Controllers\Webhooks\DuitkuWebhookController;
use App\Http\Controllers\Webhooks\TripayWebhookController;
use App\Http\Controllers\Webhooks\XenditWebhookController;
use App\Http\Controllers\Website\WebsiteMessageController;
use App\Http\Controllers\Website\WebsitePageController;
use App\Http\Controllers\Website\WebsitePostController;
use App\Http\Controllers\Website\WebsiteSettingController;
use App\Http\Controllers\WhatsappController;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

// Public storage fallback route (serves files directly when webserver symlink is bypassed)
Route::get('/storage/{path}', StorageServeController::class)
    ->where('path', '.*')
    ->name('storage.serve');

Route::get('/', [PublicSiteController::class, 'home'])
    ->middleware('public.site')
    ->name('home');

// Public tenant site content (blog & static pages). Rendered only on tenant
// domains; platform hosts fall back to the vendor home inside the controller.
Route::get('/berita', [PublicSiteController::class, 'posts'])
    ->middleware('public.site')
    ->name('public.posts');
Route::get('/berita/{slug}', [PublicSiteController::class, 'post'])
    ->middleware('public.site')
    ->name('public.post');
Route::get('/p/{slug}', [PublicSiteController::class, 'page'])
    ->middleware('public.site')
    ->name('public.page');

// Public contact page + submission (rate-limited; honeypot inside the form).
Route::get('/kontak', [PublicSiteController::class, 'contact'])
    ->middleware('public.site')
    ->name('public.contact');
Route::post('/kontak', [PublicSiteController::class, 'storeMessage'])
    ->middleware(['public.site', 'throttle:10,1'])
    ->name('public.contact.store');

// SEO endpoints: dynamic per-host sitemap & robots (no static files, so the
// webserver never shadows these routes).
Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap'])
    ->middleware('public.site')
    ->name('public.sitemap');
Route::get('/robots.txt', [PublicSiteController::class, 'robots'])
    ->middleware('public.site')
    ->name('public.robots');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp'])->middleware('throttle:5,1')->name('password.otp.send');
    Route::get('/forgot-password/otp', [ForgotPasswordController::class, 'showOtpForm'])->name('password.otp.form');
    Route::post('/forgot-password/otp/resend', [ForgotPasswordController::class, 'resendOtp'])->middleware('throttle:5,1')->name('password.otp.resend');
    Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:5,1')->name('password.otp.verify');
    Route::get('/forgot-password/reset', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset.form');
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.reset');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/auth/impersonate/{token}', [ImpersonationController::class, 'consume'])->name('auth.impersonate');
Route::post('/auth/impersonate/leave', [ImpersonationController::class, 'leave'])->name('auth.impersonate.leave');

Route::post('/tripay/callback', TripayWebhookController::class)->name('tripay.callback');
Route::post('/duitku/callback', DuitkuWebhookController::class)->name('duitku.callback');
Route::post('/xendit/callback', XenditWebhookController::class)->name('xendit.callback');

Route::middleware(['auth', 'province.user'])->prefix('province')->name('province.')->group(function (): void {
    Route::get('/dashboard', [ProvinceDashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports/pack', [ProvinceReportController::class, 'pack'])->name('reports.pack');
    Route::get('/reports/balance-sheet', [ProvinceReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/income-statement', [ProvinceReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('/reports/cash-flow', [ProvinceReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/equity-changes', [ProvinceReportController::class, 'equityChanges'])->name('reports.equity-changes');
    Route::get('/reports/calk', [ProvinceReportController::class, 'calk'])->name('reports.calk');
    Route::get('/reports/pdf', [ProvinceReportController::class, 'pdf'])->name('reports.pdf');
});

Route::middleware(['auth', 'regency.user'])->prefix('regency')->name('regency.')->group(function (): void {
    Route::get('/dashboard', [RegencyDashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports/balance-sheet', [RegencyReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/income-statement', [RegencyReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('/reports/general-ledger', [RegencyReportController::class, 'generalLedger'])->name('reports.general-ledger');
    Route::get('/reports/cash-flow', [RegencyReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/calk', [RegencyReportController::class, 'calk'])->name('reports.calk');
    Route::get('/reports/{type}/pdf', [RegencyReportController::class, 'pdf'])->name('reports.pdf');
});

Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');

    Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/create', [AdminTenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [AdminTenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [AdminTenantController::class, 'show'])->name('tenants.show');
    Route::get('/tenants/{tenant}/edit', [AdminTenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/tenants/{tenant}', [AdminTenantController::class, 'update'])->name('tenants.update');
    Route::post('/tenants/{tenant}/suspend', [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/activate', [AdminTenantController::class, 'activate'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/impersonate', [AdminTenantImpersonationController::class, 'impersonate'])->name('tenants.impersonate');
    Route::post('/tenants/{tenant}/repair', [AdminTenantController::class, 'repair'])->name('tenants.repair');
    Route::post('/tenants/{tenant}/subscription', [AdminTenantController::class, 'assignSubscription'])->name('tenants.subscription');

    Route::get('/tenants/{tenant}/users', [AdminTenantUserController::class, 'index'])->name('tenants.users.index');
    Route::get('/tenants/{tenant}/users/create', [AdminTenantUserController::class, 'create'])->name('tenants.users.create');
    Route::post('/tenants/{tenant}/users', [AdminTenantUserController::class, 'store'])->name('tenants.users.store');
    Route::get('/tenants/{tenant}/users/{user}/edit', [AdminTenantUserController::class, 'edit'])->name('tenants.users.edit');
    Route::put('/tenants/{tenant}/users/{user}', [AdminTenantUserController::class, 'update'])->name('tenants.users.update');
    Route::post('/tenants/{tenant}/users/{user}/reset-password', [AdminTenantUserController::class, 'resetPassword'])->name('tenants.users.reset-password');

    Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [AdminPlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [AdminPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');

    Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [AdminInvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [AdminInvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/void', [AdminInvoiceController::class, 'void'])->name('invoices.void');
    Route::post('/invoices/{invoice}/toggle-blocking', [AdminInvoiceController::class, 'toggleBlocking'])->name('invoices.toggle-blocking');
    Route::post('/invoices/{invoice}/payments/manual', [AdminInvoicePaymentController::class, 'storeManual'])->name('invoices.payments.manual');
    Route::post('/invoices/{invoice}/payments/tripay', [AdminInvoicePaymentController::class, 'storeTripay'])->name('invoices.payments.tripay');
    Route::get('/revenue', AdminRevenueController::class)->name('revenue.index');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/shards', [ShardController::class, 'index'])->name('shards.index');

    Route::get('/whatsapp', [PlatformWhatsappController::class, 'index'])->name('whatsapp.index');
    Route::post('/whatsapp', [PlatformWhatsappController::class, 'store'])->name('whatsapp.store');
    Route::put('/whatsapp/{instance}', [PlatformWhatsappController::class, 'update'])->name('whatsapp.update');
    Route::delete('/whatsapp/{instance}', [PlatformWhatsappController::class, 'destroy'])->name('whatsapp.destroy');
    Route::post('/whatsapp/{instance}/create-session', [PlatformWhatsappController::class, 'createSession'])->name('whatsapp.create-session');
    Route::get('/whatsapp/{instance}/state', [PlatformWhatsappController::class, 'state'])->name('whatsapp.state');
    Route::delete('/whatsapp/{instance}/delete-session', [PlatformWhatsappController::class, 'deleteSession'])->name('whatsapp.delete-session');
    Route::post('/whatsapp/{instance}/test', [PlatformWhatsappController::class, 'test'])->name('whatsapp.test');
    Route::post('/whatsapp/{instance}/set-default', [PlatformWhatsappController::class, 'setDefault'])->name('whatsapp.set-default');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');

    Route::get('/settings', [PlatformSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [PlatformSettingController::class, 'update'])->name('settings.update');
    Route::delete('/settings', [PlatformSettingController::class, 'destroy'])->name('settings.destroy');

    Route::get('/migration', [AdminMigrationController::class, 'index'])->name('migration.index');
    Route::get('/migrations', [AdminMigrationController::class, 'index'])->name('migrations.index');
    Route::get('/migration/discover', [AdminMigrationController::class, 'discover'])->name('migration.discover');
    Route::get('/migration/legacy-tenants', [AdminMigrationController::class, 'legacyTenants'])->name('migration.legacy-tenants');
    Route::post('/migrations', [AdminMigrationController::class, 'store'])->name('migrations.store');
    Route::get('/migrations/{run}', [AdminMigrationController::class, 'show'])->name('migrations.show');
    Route::get('/migrations/{run}/stream', [AdminMigrationController::class, 'stream'])->name('migrations.stream');

    Route::get('/regional/provinces', [RegionalCodeController::class, 'provinces'])->name('regional.provinces');
    Route::get('/regional/regencies/{province}', [RegionalCodeController::class, 'regencies'])->name('regional.regencies');
    Route::get('/regional/districts/{regency}', [RegionalCodeController::class, 'districts'])->name('regional.districts');
    Route::get('/regional/regency-center/{regency}', [RegionalCodeController::class, 'regencyCenter'])->name('regional.regency-center');
    Route::get('/regional/villages/{district}', [RegionalCodeController::class, 'villages'])->name('regional.villages');

    // Payment Gateways
    Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
    Route::post('/payment-gateways/active', [PaymentGatewayController::class, 'updateActiveGateway'])->name('payment-gateways.active');
    Route::post('/payment-gateways/tripay', [PaymentGatewayController::class, 'updateTripay'])->name('payment-gateways.tripay');
    Route::post('/payment-gateways/tripay/test', [PaymentGatewayController::class, 'testTripay'])->name('payment-gateways.tripay.test');
    Route::post('/payment-gateways/duitku', [PaymentGatewayController::class, 'updateDuitku'])->name('payment-gateways.duitku');
    Route::post('/payment-gateways/duitku/test', [PaymentGatewayController::class, 'testDuitku'])->name('payment-gateways.duitku.test');
    Route::post('/payment-gateways/xendit', [PaymentGatewayController::class, 'updateXendit'])->name('payment-gateways.xendit');
    Route::post('/payment-gateways/xendit/test', [PaymentGatewayController::class, 'testXendit'])->name('payment-gateways.xendit.test');

    // AI Assistant
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai-assistant.index');
    Route::get('/ai-assistant/personas', [AiAssistantController::class, 'personas'])->name('ai-assistant.personas');
    Route::post('/ai-assistant/personas/store', [AiAssistantController::class, 'storePersona'])->name('ai-assistant.personas.store');
    Route::post('/ai-assistant/personas', [AiAssistantController::class, 'storePersona']);
    Route::put('/ai-assistant/personas/{id}', [AiAssistantController::class, 'updatePersona'])->name('ai-assistant.personas.update');
    Route::delete('/ai-assistant/personas/{id}', [AiAssistantController::class, 'deletePersona'])->name('ai-assistant.personas.delete');
    Route::post('/ai-assistant/personas/{id}/toggle', [AiAssistantController::class, 'togglePersona'])->name('ai-assistant.personas.toggle');
    Route::get('/ai-assistant/tools', [AiAssistantController::class, 'tools'])->name('ai-assistant.tools');
    Route::post('/ai-assistant/tools/sync', [AiAssistantController::class, 'syncTools'])->name('ai-assistant.tools.sync');
    Route::put('/ai-assistant/tools/{id}', [AiAssistantController::class, 'updateTool'])->name('ai-assistant.tools.update');
    Route::get('/ai-assistant/documents', [AiAssistantController::class, 'documents'])->name('ai-assistant.documents');
    Route::get('/ai-assistant/documents/{id}', [AiAssistantController::class, 'documentDetail'])->name('ai-assistant.documents.show');
    Route::delete('/ai-assistant/documents/{id}', [AiAssistantController::class, 'deleteDocument'])->name('ai-assistant.documents.delete');
    Route::post('/ai-assistant/upload', [AiAssistantController::class, 'uploadDocument'])->name('ai-assistant.upload');
    Route::post('/ai-assistant/chat', [AiAssistantController::class, 'chatStream'])->name('ai-assistant.chat');
    Route::get('/ai-assistant/conversations', [AiAssistantController::class, 'conversations'])->name('ai-assistant.conversations');
    Route::get('/ai-assistant/audit-logs', [AiAssistantController::class, 'auditLogs'])->name('ai-assistant.audit-logs');
    // Backward compatibility for Orchestrator AI routes
    Route::get('/integrations/orchestrator/personas', [AiAssistantController::class, 'personas']);
    Route::post('/integrations/orchestrator/personas/store', [AiAssistantController::class, 'storePersona']);
    Route::put('/integrations/orchestrator/personas/{id}', [AiAssistantController::class, 'updatePersona']);
    Route::delete('/integrations/orchestrator/personas/{id}', [AiAssistantController::class, 'deletePersona']);
    Route::post('/integrations/orchestrator/personas/{id}/toggle', [AiAssistantController::class, 'togglePersona']);
    Route::get('/integrations/orchestrator/tools', [AiAssistantController::class, 'tools']);
    Route::post('/integrations/orchestrator/tools/sync', [AiAssistantController::class, 'syncTools']);
    Route::put('/integrations/orchestrator/tools/{id}', [AiAssistantController::class, 'updateTool']);
    Route::get('/integrations/orchestrator/documents', [AiAssistantController::class, 'documents']);
    Route::get('/integrations/orchestrator/documents/{id}', [AiAssistantController::class, 'documentDetail']);
    Route::delete('/integrations/orchestrator/documents/{id}', [AiAssistantController::class, 'deleteDocument']);
    Route::post('/integrations/orchestrator/upload', [AiAssistantController::class, 'uploadDocument']);
    Route::post('/integrations/orchestrator/chat', [AiAssistantController::class, 'chatStream']);
    Route::get('/integrations/orchestrator/conversations', [AiAssistantController::class, 'conversations']);
    Route::get('/integrations/orchestrator/audit-logs', [AiAssistantController::class, 'auditLogs']);

    // Backward compatibility aliases
    Route::get('/integrations', fn () => redirect()->route('payment-gateways.index'))->name('integrations.index');
    Route::get('/integrations/orchestrator', fn () => redirect()->route('ai-assistant.index'));
    Route::post('/integrations/active-gateway', [PaymentGatewayController::class, 'updateActiveGateway'])->name('integrations.active-gateway');
    Route::post('/integrations/tripay', [PaymentGatewayController::class, 'updateTripay'])->name('integrations.tripay');
    Route::post('/integrations/tripay/test', [PaymentGatewayController::class, 'testTripay'])->name('integrations.tripay.test');
    Route::post('/integrations/duitku', [PaymentGatewayController::class, 'updateDuitku'])->name('integrations.duitku');
    Route::post('/integrations/duitku/test', [PaymentGatewayController::class, 'testDuitku'])->name('integrations.duitku.test');
    Route::post('/integrations/xendit', [PaymentGatewayController::class, 'updateXendit'])->name('integrations.xendit');
    Route::post('/integrations/xendit/test', [PaymentGatewayController::class, 'testXendit'])->name('integrations.xendit.test');

    // Onboarding & Saldo Awal Ã¢â‚¬â€ superadmin only, per-tenant.
    // {tenant} numeric row_id (resolved via TenantResolver::resolveById).
    // Middleware 'tenant' agar TenantContext terinisialisasi (Account/JournalEntry
    // model pakai TenantScope). validateTenantAccess() membypass superadmin.
    Route::prefix('tenants/{tenant}')->name('tenants.')->group(function (): void {
        Route::middleware(['superadmin', 'tenant'])->group(function (): void {
            Route::get('/onboarding/import', [TenantOnboardingImportController::class, 'index'])->name('onboarding.import');
            Route::post('/onboarding/opening-balances', [TenantOnboardingImportController::class, 'saveOpeningBalances'])->name('onboarding.opening-balances');
            Route::post('/onboarding/opening-balances/manual', [TenantOnboardingImportController::class, 'saveManualOpening'])->name('onboarding.opening-balances.manual');
            Route::post('/onboarding/aggregate-journal', [TenantOnboardingImportController::class, 'saveAggregateJournal'])->name('onboarding.aggregate-journal');
            Route::post('/onboarding/active-loans', [TenantOnboardingImportController::class, 'importActiveLoans'])->name('onboarding.active-loans');
            Route::get('/onboarding/templates/{type}', [TenantOnboardingImportController::class, 'downloadTemplate'])->name('onboarding.templates');

            // Data Purifier & Training Reset Ã¢â‚¬â€ superadmin only, per-tenant.
            Route::get('/data-purifier', [TenantDataPurifierController::class, 'index'])->name('data-purifier.index');
            Route::post('/data-purifier/start-training', [TenantDataPurifierController::class, 'startTraining'])->name('data-purifier.start-training');
            Route::post('/data-purifier/end-training', [TenantDataPurifierController::class, 'endTraining'])->name('data-purifier.end-training');
            Route::post('/data-purifier/purge', [TenantDataPurifierController::class, 'purge'])->name('data-purifier.purge');
            Route::post('/data-purifier/reset-training', [TenantDataPurifierController::class, 'resetTraining'])->name('data-purifier.reset-training');
        });
    });
});

Route::middleware(['auth', 'tenant', 'subscription.active'])->group(function (): void {
    if (file_exists(base_path('vendor/enpii/assistant/routes/api.php'))) {
        Route::prefix('assistant')->group(function (): void {
            require base_path('vendor/enpii/assistant/routes/api.php');
        });
    }

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');
    Route::get('/search', SearchController::class)->name('search');

    Route::prefix('portal')
        ->name('portal.')
        ->middleware('permission:portal.self')
        ->group(function (): void {
            Route::get('/', [PortalMemberController::class, 'index'])->name('index');
        });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/account', [ProfileController::class, 'updateAccount'])->name('profile.account.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');

    Route::prefix('billing')->name('billing.')->group(function (): void {
        Route::get('/invoices', [TenantInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [TenantInvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{invoice}/checkout/tripay', [TenantInvoiceController::class, 'checkoutTripay'])->name('invoices.checkout.tripay');
        Route::post('/invoices/{invoice}/pay', [TenantInvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('/invoices/{invoice}/check-status', [TenantInvoiceController::class, 'checkStatus'])->name('invoices.check-status');
    });

    // Website (public site content: blog & static pages)
    Route::prefix('website')->name('website.')->group(function (): void {
        Route::get('/posts', [WebsitePostController::class, 'index'])->name('posts.index');
        Route::get('/posts/create', [WebsitePostController::class, 'create'])->name('posts.create');
        Route::post('/posts', [WebsitePostController::class, 'store'])->name('posts.store');
        Route::get('/posts/{post}/edit', [WebsitePostController::class, 'edit'])->name('posts.edit');
        Route::put('/posts/{post}', [WebsitePostController::class, 'update'])->name('posts.update');
        Route::delete('/posts/{post}', [WebsitePostController::class, 'destroy'])->name('posts.destroy');
        Route::post('/posts/{postId}/restore', [WebsitePostController::class, 'restore'])->whereNumber('postId')->name('posts.restore');
        Route::delete('/posts/{post}/cover', [WebsitePostController::class, 'removeCover'])->name('posts.cover.destroy');

        Route::get('/pages', [WebsitePageController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [WebsitePageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [WebsitePageController::class, 'store'])->name('pages.store');
        Route::get('/pages/{page}/edit', [WebsitePageController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{page}', [WebsitePageController::class, 'update'])->name('pages.update');
        Route::delete('/pages/{page}', [WebsitePageController::class, 'destroy'])->name('pages.destroy');
        Route::post('/pages/{pageId}/restore', [WebsitePageController::class, 'restore'])->whereNumber('pageId')->name('pages.restore');

        // Site appearance & contact settings for the tenant's public site
        Route::get('/settings', [WebsiteSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [WebsiteSettingController::class, 'update'])->name('settings.update');

        // Inbox for the public contact form
        Route::get('/messages', [WebsiteMessageController::class, 'index'])->name('messages.index');
        Route::post('/messages/{message}/read', [WebsiteMessageController::class, 'markRead'])->name('messages.read');
        Route::delete('/messages/{message}', [WebsiteMessageController::class, 'destroy'])->name('messages.destroy');
    });

    // Master Data
    Route::prefix('master-data')->name('master-data.')->group(function (): void {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::get('/members/lookup', [MemberController::class, 'lookup'])->name('members.lookup');
        Route::get('/members/export', [MemberController::class, 'export'])->name('members.export');
        Route::post('/members/import', [MemberController::class, 'import'])->name('members.import');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::post('/members/{member}/identity-photo', [MemberController::class, 'uploadIdentityPhoto'])->name('members.identity-photo.store');
        Route::delete('/members/{member}/identity-photo', [MemberController::class, 'destroyIdentityPhoto'])->name('members.identity-photo.destroy');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
        Route::get('/groups/lookup', [GroupController::class, 'lookup'])->name('groups.lookup');
        Route::get('/groups/export', [GroupController::class, 'export'])->name('groups.export');
        Route::post('/groups/import', [GroupController::class, 'import'])->name('groups.import');
        Route::get('/groups/member-options', [GroupController::class, 'memberOptions'])->name('groups.member-options');
        Route::post('/groups/members', [GroupController::class, 'storeMember'])->name('groups.members.store');
        Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
        Route::get('/groups/{group}', [GroupController::class, 'show'])->whereNumber('group')->name('groups.show');
        Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->whereNumber('group')->name('groups.edit');
        Route::put('/groups/{group}', [GroupController::class, 'update'])->whereNumber('group')->name('groups.update');
        Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->whereNumber('group')->name('groups.destroy');

        Route::get('/villages', [VillageController::class, 'index'])->name('villages.index');
        Route::get('/villages/{village}/edit', [VillageController::class, 'edit'])->name('villages.edit');
        Route::put('/villages/{village}', [VillageController::class, 'update'])->name('villages.update');

        Route::get('/institutions', [OtherInstitutionController::class, 'index'])->name('institutions.index');
        Route::get('/institutions/create', [OtherInstitutionController::class, 'create'])->name('institutions.create');
        Route::get('/institutions/export', [OtherInstitutionController::class, 'export'])->name('institutions.export');
        Route::post('/institutions/import', [OtherInstitutionController::class, 'import'])->name('institutions.import');
        Route::post('/institutions', [OtherInstitutionController::class, 'store'])->name('institutions.store');
        Route::get('/institutions/{institution}', [OtherInstitutionController::class, 'show'])->name('institutions.show');
        Route::get('/institutions/{institution}/edit', [OtherInstitutionController::class, 'edit'])->name('institutions.edit');
        Route::put('/institutions/{institution}', [OtherInstitutionController::class, 'update'])->name('institutions.update');
    });

    // Membership Aliases
    Route::prefix('membership')->name('membership.')->group(function (): void {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');

        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
        Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
        Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
        Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
        Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    });

    // Lending
    Route::get('/lending/proposals', [LoanController::class, 'index'])->name('lending.proposals.index');
    Route::get('/lending/proposals/create', [LoanController::class, 'create'])->name('lending.proposals.create');
    Route::post('/lending/proposals', [LoanController::class, 'store'])->name('lending.proposals.store');
    Route::get('/lending/proposals/{proposal}', [LoanController::class, 'show'])->name('lending.proposals.show');
    Route::get('/lending/proposals/{proposal}/edit', [LoanController::class, 'edit'])->name('lending.proposals.edit');
    Route::put('/lending/proposals/{proposal}', [LoanController::class, 'update'])->name('lending.proposals.update');
    Route::post('/lending/proposals/{proposal}/submit', [LoanController::class, 'submit'])->name('lending.proposals.submit');
    Route::post('/lending/proposals/{proposal}/approve', [LoanController::class, 'approve'])->name('lending.proposals.approve');
    Route::post('/lending/proposals/{proposal}/reject', [LoanController::class, 'reject'])->name('lending.proposals.reject');

    Route::get('/lending/simulation', [LoanSimulationController::class, 'index'])->name('lending.simulation.index');
    Route::post('/lending/simulation/calculate', [LoanSimulationController::class, 'calculate'])->name('lending.simulation.calculate');
    Route::get('/lending/simulation/pdf', [LoanSimulationController::class, 'pdf'])->name('lending.simulation.pdf');

    Route::get('/lending/loans', [LoanController::class, 'index'])->name('lending.loans.index');
    Route::get('/lending/loans/create', [LoanController::class, 'create'])->name('lending.loans.create');
    Route::post('/lending/loans', [LoanController::class, 'store'])->name('lending.loans.store');
    Route::get('/lending/loans/pdf', [LoanController::class, 'exportPdf'])->name('lending.loans.pdf');
    Route::get('/lending/loans/{loan}', [LoanController::class, 'show'])->name('lending.loans.show');
    Route::get('/lending/loans/{loan}/card', [LoanController::class, 'card'])->name('lending.loans.card');
    Route::get('/lending/loans/{loan}/card/reprint', [LoanController::class, 'cardReprint'])->name('lending.loans.card.reprint');
    Route::get('/lending/loans/{loan}/edit', [LoanController::class, 'edit'])->name('lending.loans.edit');
    Route::get('/lending/loans/{loan}/documents/{type}', [LoanDocumentController::class, 'document'])
        ->where('type', '[a-z_]+')
        ->name('lending.loans.documents.print');
    Route::put('/lending/loans/{loan}', [LoanController::class, 'update'])->name('lending.loans.update');
    Route::delete('/lending/loans/{loan}', [LoanController::class, 'destroy'])->name('lending.loans.destroy');
    Route::delete('/lending/proposals/{proposal}', [LoanController::class, 'destroy'])->name('lending.proposals.destroy');
    Route::delete('/lending/loans/{loan}/beneficiaries/{member}', [LoanController::class, 'removeBeneficiary'])->name('lending.loans.beneficiaries.destroy');
    Route::patch('/lending/loans/{loan}/verify', [LoanController::class, 'verify'])->name('lending.loans.verify');
    Route::patch('/lending/loans/{loan}/approve', [LoanController::class, 'approve'])->name('lending.loans.approve');
    Route::patch('/lending/loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('lending.loans.disburse');
    Route::post('/lending/loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('lending.loans.disburse.post');
    Route::patch('/lending/loans/{loan}/revert', [LoanController::class, 'revert'])->name('lending.loans.revert');
    Route::patch('/lending/loans/{loan}/committee', [LoanController::class, 'setCommittee'])->name('lending.loans.committee');
    Route::post('/lending/loans/{loan}/reschedule', [LoanController::class, 'reschedule'])->name('lending.loans.reschedule');
    Route::post('/lending/loans/{loan}/cancel-reschedule', [LoanController::class, 'cancelReschedule'])->name('lending.loans.cancel-reschedule');
    Route::post('/lending/loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('lending.loans.write-off');
    Route::post('/lending/loans/{loan}/beneficiaries/{member}/write-off', [LoanController::class, 'writeOffBeneficiary'])
        ->whereNumber('member')
        ->name('lending.loans.beneficiaries.write-off');
    Route::patch('/lending/loans/{loan}/complete', [LoanController::class, 'complete'])->name('lending.loans.complete');

    Route::get('/lending/payments/create', [LoanController::class, 'create'])->name('lending.payments.create');
    Route::post('/lending/payments', [LoanController::class, 'store'])->name('lending.payments.store');

    Route::prefix('lending/reports')->name('lending.reports.')->group(function (): void {
        Route::get('/portfolio', [LoanReportController::class, 'portfolio'])->name('portfolio');
        Route::get('/portfolio/pdf', [LoanReportController::class, 'portfolioPdf'])->name('portfolio.pdf');
        Route::get('/schedule-vs-actual', [LoanReportController::class, 'scheduleVsActual'])->name('schedule-vs-actual');
        Route::get('/schedule-vs-actual/pdf', [LoanReportController::class, 'scheduleVsActualPdf'])->name('schedule-vs-actual.pdf');
        Route::get('/lpp-desa', [LoanReportController::class, 'lppDesa'])->name('lpp-desa');
        Route::get('/lpp-desa/pdf', [LoanReportController::class, 'lppDesaPdf'])->name('lpp-desa.pdf');
        Route::get('/lpp-kelompok', [LoanReportController::class, 'lppKelompok'])->name('lpp-kelompok');
        Route::get('/lpp-kelompok/pdf', [LoanReportController::class, 'lppKelompokPdf'])->name('lpp-kelompok.pdf');
        Route::get('/kolek-desa', [LoanReportController::class, 'kolekDesa'])->name('kolek-desa');
        Route::get('/kolek-desa/pdf', [LoanReportController::class, 'kolekDesaPdf'])->name('kolek-desa.pdf');
        Route::get('/cadangan-penghapusan', [LoanReportController::class, 'cadanganPenghapusan'])->name('cadangan-penghapusan');
        Route::get('/cadangan-penghapusan/pdf', [LoanReportController::class, 'cadanganPenghapusanPdf'])->name('cadangan-penghapusan.pdf');
    });

    // Accounting
    Route::redirect('/assets', '/accounting/assets', 301);
    Route::get('/assets/{asset}', fn (string $asset) => redirect("/accounting/assets/{$asset}", 301));
    Route::get('/assets/{asset}/edit', fn (string $asset) => redirect("/accounting/assets/{$asset}/edit", 301));

    Route::prefix('accounting')->name('accounting.')->group(function (): void {
        Route::get('/journals', [JournalBrowseController::class, 'index'])->name('journals.index');
        Route::post('/journals/bulk-reverse', [JournalBrowseController::class, 'bulkReverse'])->name('journals.bulk-reverse');
        Route::get('/journals/{entry}/edit', [JournalBrowseController::class, 'edit'])->name('journals.edit');
        Route::put('/journals/{entry}', [JournalBrowseController::class, 'update'])->name('journals.update');
        Route::post('/journals/{entry}/reverse', [JournalBrowseController::class, 'reverse'])->name('journals.reverse');

        // Bukti Kas (BKM/BKK/BM)
        Route::get('/journals/{entry}/cash-evidence-kind', [CashEvidenceController::class, 'kind'])->name('journals.cash-evidence.kind');
        Route::get('/journals/{entry}/cash-evidence', [CashEvidenceController::class, 'document'])->name('journals.cash-evidence');
        Route::get('/journals/{entry}/cash-evidence/{kind}', [CashEvidenceController::class, 'document'])
            ->where('kind', '[A-Z]{3}')
            ->name('journals.cash-evidence.kind-explicit');

        Route::get('/journal-entries', [JournalBrowseController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])->name('journal-entries.create');
        Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
        Route::get('/journal-entries/installment', [JournalEntryController::class, 'installment'])->name('journal-entries.installment');
        Route::post('/journal-entries/installment', [JournalEntryController::class, 'storeInstallment'])->name('journal-entries.installment.store');
        Route::get('/journal-entries/{entry}/installment-receipt', [JournalEntryController::class, 'installmentReceipt'])->name('journal-entries.installment.receipt');
        Route::get('/loans/{loan}/group-detail', [JournalEntryController::class, 'loanGroupDetail'])->name('loans.group-detail');
        Route::get('/loans/{loan}/installment-history', [JournalEntryController::class, 'loanInstallmentHistory'])->name('loans.installment-history');
        Route::get('/loans/{loan}/member-options', [JournalEntryController::class, 'groupMemberOptions'])->name('loans.member-options');

        Route::get('/chart-of-accounts', [ChartOfAccountsController::class, 'index'])->name('chart-of-accounts.index');
        Route::get('/accounts', [ChartOfAccountsController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/create', [ChartOfAccountsController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [ChartOfAccountsController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}/edit', [ChartOfAccountsController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{account}', [ChartOfAccountsController::class, 'update'])->name('accounts.update');

        // Assets
        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('assets.show');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->whereNumber('asset')->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->whereNumber('asset')->name('assets.update');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->whereNumber('asset')->name('assets.destroy');

        // Period Close
        Route::get('/period-close', [PeriodCloseController::class, 'index'])->name('period-close.index');
        Route::post('/period-close/{year}/{month}/close', [PeriodCloseController::class, 'closeMonth'])
            ->whereNumber(['year', 'month'])
            ->name('period-close.month.close');
        Route::post('/period-close/{year}/{month}/reopen', [PeriodCloseController::class, 'reopenMonth'])
            ->whereNumber(['year', 'month'])
            ->name('period-close.month.reopen');
        Route::post('/period-close/year-close', [PeriodCloseController::class, 'closeYear'])->name('period-close.year.close');
        Route::post('/period-close/allocate', [PeriodCloseController::class, 'allocate'])->name('period-close.allocate');

        // Tax Estimate
        Route::get('/tax-estimate', [TaxEstimateController::class, 'index'])->name('tax-estimate');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function (): void {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/journals', [ReportController::class, 'journals'])->name('journals');
            Route::get('/journals/pdf', [ReportController::class, 'journalsPdf'])->name('journals.pdf');
            Route::get('/journals/excel', [ReportController::class, 'journalsExcel'])->name('journals.excel');
            Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/trial-balance/pdf', [ReportController::class, 'trialBalancePdf'])->name('trial-balance.pdf');
            Route::get('/trial-balance/excel', [ReportController::class, 'trialBalanceExcel'])->name('trial-balance.excel');
            Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/balance-sheet/pdf', [ReportController::class, 'balanceSheetPdf'])->name('balance-sheet.pdf');
            Route::get('/balance-sheet/excel', [ReportController::class, 'balanceSheetExcel'])->name('balance-sheet.excel');
            Route::get('/income-statement', [ReportController::class, 'incomeStatement'])->name('income-statement');
            Route::get('/income-statement/pdf', [ReportController::class, 'incomeStatementPdf'])->name('income-statement.pdf');
            Route::get('/income-statement/excel', [ReportController::class, 'incomeStatementExcel'])->name('income-statement.excel');
            Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/cash-flow/pdf', [ReportController::class, 'cashFlowPdf'])->name('cash-flow.pdf');
            Route::get('/cash-flow/excel', [ReportController::class, 'cashFlowExcel'])->name('cash-flow.excel');
            Route::get('/equity-change', [ReportController::class, 'equityChange'])->name('equity-change');
            Route::get('/equity-change/pdf', [ReportController::class, 'equityChangePdf'])->name('equity-change.pdf');
            Route::get('/equity-change/excel', [ReportController::class, 'equityChangeExcel'])->name('equity-change.excel');
            Route::get('/calk', [ReportController::class, 'calk'])->name('calk');
            Route::get('/calk/pdf', [ReportController::class, 'calkPdf'])->name('calk.pdf');
            Route::put('/calk/notes', [ReportController::class, 'saveCalkNotes'])->name('calk.notes');
            Route::get('/general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
            Route::get('/general-ledger/pdf', [ReportController::class, 'generalLedgerPdf'])->name('general-ledger.pdf');
            Route::get('/general-ledger/excel', [ReportController::class, 'generalLedgerExcel'])->name('general-ledger.excel');
            Route::get('/financial-health', [ReportController::class, 'financialHealth'])->name('financial-health');
            Route::get('/financial-health/pdf', [ReportController::class, 'financialHealthPdf'])->name('financial-health.pdf');
            Route::get('/assets/fixed/pdf', [ReportController::class, 'fixedAssetsPdf'])->name('assets.fixed.pdf');
            Route::get('/assets/fixed/excel', [ReportController::class, 'fixedAssetsExcel'])->name('assets.fixed.excel');
            Route::get('/assets/intangible/pdf', [ReportController::class, 'intangibleAssetsPdf'])->name('assets.intangible.pdf');
            Route::get('/assets/intangible/excel', [ReportController::class, 'intangibleAssetsExcel'])->name('assets.intangible.excel');
            Route::get('/annual-pack', [ReportController::class, 'annualPack'])->name('annual-pack');
            Route::get('/annual-pack/cover/pdf', [ReportController::class, 'annualCoverPdf'])->name('annual-pack.cover.pdf');
            Route::get('/annual-pack/surat-pengantar/pdf', [ReportController::class, 'annualSuratPengantarPdf'])->name('annual-pack.surat-pengantar.pdf');
            Route::get('/annual-pack/ba-pergantian/pdf', [ReportController::class, 'annualBaPergantianPdf'])->name('annual-pack.ba-pergantian.pdf');
            Route::get('/annual-pack/mou/pdf', [ReportController::class, 'annualMouPdf'])->name('annual-pack.mou.pdf');
            Route::get('/bundle/pdf', [ReportController::class, 'bundlePdf'])->name('bundle.pdf');
        });
    });

    // Budgeting
    Route::prefix('budgeting')->name('budgeting.')->group(function (): void {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::post('/{year}/{month}', [BudgetController::class, 'save'])
            ->whereNumber(['year', 'month'])
            ->name('save');
        Route::post('/{year}/{month}/copy-previous', [BudgetController::class, 'copyPrevious'])
            ->whereNumber(['year', 'month'])
            ->name('copy-previous');
        Route::post('/{year}/approve', [BudgetController::class, 'approve'])
            ->whereNumber('year')
            ->name('approve');
        Route::post('/{year}/reopen', [BudgetController::class, 'reopen'])
            ->whereNumber('year')
            ->name('reopen');
    });

    // Access Management (RBAC: Users & Roles)
    Route::prefix('access')->name('access.')->group(function (): void {
        // Users
        Route::get('/users', [TenantUserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [TenantUserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [TenantUserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [TenantUserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [TenantUserManagementController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-password', [TenantUserManagementController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('/users/{user}', [TenantUserManagementController::class, 'destroy'])->name('users.destroy');

        // Roles
        Route::get('/roles', [TenantRoleManagementController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [TenantRoleManagementController::class, 'create'])->name('roles.create');
        Route::post('/roles', [TenantRoleManagementController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [TenantRoleManagementController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [TenantRoleManagementController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [TenantRoleManagementController::class, 'destroy'])->name('roles.destroy');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/identity', [SettingsController::class, 'updateIdentity'])->name('identity.update');
        Route::put('/lending-system', [SettingsController::class, 'updateLendingSystem'])->name('lending-system.update');
        Route::post('/lending-system/sync-rounding', [SettingsController::class, 'syncRounding'])->name('lending-system.sync-rounding');
        Route::post('/logo', [SettingsController::class, 'updateLogo'])->name('logo.update');
        Route::delete('/logo', [SettingsController::class, 'destroyLogo'])->name('logo.destroy');
        Route::put('/whatsapp', [SettingsController::class, 'updateWhatsapp'])->name('whatsapp.update');
        Route::put('/offline-access', [SettingsController::class, 'updateOfflineAccess'])->name('offline-access.update');
        Route::get('/offline-users', [SettingsController::class, 'offlineUsers'])->name('offline-users.index');
        Route::post('/whatsapp/create', [SettingsController::class, 'createWhatsappInstance'])->name('whatsapp.create');
        Route::delete('/whatsapp/delete', [SettingsController::class, 'deleteWhatsappInstance'])->name('whatsapp.delete');
        Route::get('/whatsapp/state', [SettingsController::class, 'instanceState'])->name('whatsapp.state');
        Route::post('/whatsapp/test', [SettingsController::class, 'testWhatsapp'])->name('whatsapp.test');
        Route::post('/whatsapp/pair', [SettingsController::class, 'pairWhatsapp'])->name('whatsapp.pair');
        Route::put('/signatures', [SettingsController::class, 'updateSignatures'])->name('signatures.update');
        Route::post('/signatures/image', [SettingsController::class, 'storeSignatureImage'])->name('signatures.image.store');
        Route::delete('/signatures/image', [SettingsController::class, 'destroySignatureImage'])->name('signatures.image.destroy');

        // Multi-Instance WhatsApp Gateway
        Route::get('/whatsapp', [WhatsappSettingController::class, 'index'])->name('whatsapp.hub');
        Route::get('/whatsapp/manage', [WhatsappSettingController::class, 'index'])->name('whatsapp.manage');
        Route::post('/whatsapp/instances', [WhatsappSettingController::class, 'store'])->name('whatsapp.instances.store');
        Route::put('/whatsapp/instances/{instance}', [WhatsappSettingController::class, 'update'])->name('whatsapp.instances.update');
        Route::delete('/whatsapp/instances/{instance}', [WhatsappSettingController::class, 'destroy'])->name('whatsapp.instances.destroy');
        Route::post('/whatsapp/instances/{instance}/create-session', [WhatsappSettingController::class, 'createSession'])->name('whatsapp.instances.create-session');
        Route::get('/whatsapp/instances/{instance}/state', [WhatsappSettingController::class, 'state'])->name('whatsapp.instances.state');
        Route::delete('/whatsapp/instances/{instance}/delete-session', [WhatsappSettingController::class, 'deleteSession'])->name('whatsapp.instances.delete-session');
        Route::post('/whatsapp/instances/{instance}/test', [WhatsappSettingController::class, 'test'])->name('whatsapp.instances.test');
        Route::put('/whatsapp/global', [WhatsappSettingController::class, 'updateGlobal'])->name('whatsapp.global');
    });

    // Notifications (Center & Billing Notice)
    Route::get('/api/notifications', [NotificationCenterController::class, 'index'])->name('notifications.feed');
    Route::post('/api/notifications/mark-read', [NotificationCenterController::class, 'markRead'])->name('notifications.mark-read');
    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/billing', [BillingNoticeController::class, 'index'])->name('billing');
        Route::post('/billing/send', [BillingNoticeController::class, 'send'])->name('billing.send');
    });

    // WhatsApp Gateway API routes
    Route::prefix('wa')->name('wa.')->group(function (): void {
        Route::post('/send', [WhatsappController::class, 'sendMessage'])->name('send');
        Route::post('/send-bulk', [WhatsappController::class, 'sendMessages'])->name('send-bulk');
        Route::get('/history', [WhatsappController::class, 'historyMessage'])->name('history');
        Route::get('/instance-state', [WhatsappController::class, 'instanceState'])->name('instance-state');
    });

    Route::prefix('pengaturan/whatsapp')->name('pengaturan.whatsapp.')->group(function (): void {
        Route::post('/save_device', [WhatsappController::class, 'createInstance'])->name('save_device');
        Route::get('/instance_state', [WhatsappController::class, 'instanceState'])->name('instance_state');
        Route::post('/delete_session', [WhatsappController::class, 'deleteInstance'])->name('delete_session');
    });
});

// Desktop Client Sync API routes
Route::prefix('desktop')->name('desktop.')->group(function (): void {
    Route::get('/sync/status', [DesktopClientController::class, 'status'])->name('sync.status');
    Route::post('/sync/trigger', [DesktopClientController::class, 'triggerSync'])->name('sync.trigger');
});

Route::middleware(['auth', 'tenant'])
    ->prefix('t/{tenant}')
    ->get('/health', fn () => response()->json([
        'tenant_id' => app(TenantContext::class)->id(),
        'tenant_code' => app(TenantContext::class)->tenant()->code,
        'shard' => app(TenantContext::class)->shard()->code,
    ]));
