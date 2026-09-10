<?php

declare(strict_types=1);

use App\Http\Middleware\BlockOfflineMutations;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureProvinceSupervisor;
use App\Http\Middleware\EnsureRegencySupervisor;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveAssistantActor;
use App\Http\Middleware\ResolvePublicSite;
use App\Http\Middleware\VerifyDesktopApiToken;
use App\Http\Middleware\VerifyHoldingApiToken;
use App\Http\Middleware\VerifyOrchestratorSignature;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            AuthenticateSession::class,
            HandleInertiaRequests::class,
            BlockOfflineMutations::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'tripay/callback',
            'api/billing/tripay/callback',
        ]);

        $middleware->api(append: [
            BlockOfflineMutations::class,
        ]);

        // Logged-in users hitting /login: superadmin -> /admin, province -> /province/dashboard, regency -> /regency/dashboard, tenant -> /dashboard
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user !== null) {
                if ($user->is_superadmin === true) {
                    return route('admin.dashboard');
                }
                if ($user->isProvinceUser()) {
                    return route('province.dashboard');
                }
                if ($user->isRegencyUser()) {
                    return route('regency.dashboard');
                }
            }

            return route('dashboard');
        });

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'public.site' => ResolvePublicSite::class,
            'superadmin' => EnsureSuperadmin::class,
            'regency' => EnsureRegencySupervisor::class,
            'regency.user' => EnsureRegencySupervisor::class,
            'province' => EnsureProvinceSupervisor::class,
            'province.user' => EnsureProvinceSupervisor::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'permission' => EnsurePermission::class,
            'orchestrator.signature' => VerifyOrchestratorSignature::class,
            'assistant.signature' => VerifyOrchestratorSignature::class,
            'assistant.actor' => ResolveAssistantActor::class,
            'holding.auth' => VerifyHoldingApiToken::class,
            'desktop.auth' => VerifyDesktopApiToken::class,
            'offline.guard' => BlockOfflineMutations::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Register project-specific exception reporting here.
    })
    ->create();
