<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Services\OfflineAccessService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        $parts = [];
        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $parts[] = md5_file($manifest);
        }
        $envVersion = (string) env('APP_VERSION', '');
        if ($envVersion !== '') {
            $parts[] = $envVersion;
        }

        return $parts === [] ? parent::version($request) : implode('|', $parts);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $offlineAccessData = [
            'is_enabled' => false,
            'user_id' => null,
            'current_user_allowed' => false,
        ];
        try {
            $offlineContext = app(TenantContext::class);
            if ($offlineContext->isInitialized()) {
                $offlineService = app(OfflineAccessService::class);
                $offlineTenantId = $offlineContext->id();
                $offlineAccessData['is_enabled'] = $offlineService->isEnabled($offlineTenantId);
                $offlineAccessData['user_id'] = $offlineService->userId($offlineTenantId);
                $offlineAccessData['current_user_allowed'] = $user !== null
                    && $offlineService->isUserAllowed($offlineTenantId, (int) $user->row_id);
            }
        } catch (Throwable) {
        }

        return [

            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? array_merge(
                    $user->only([
                        'row_id',
                        'public_id',
                        'name',
                        'username',
                        'email',
                        'status',
                        'is_superadmin',
                        'is_regency_user',
                        'regency_code',
                        'regency_name',
                        'is_province_user',
                        'province_code',
                        'province_name',
                        'is_village_user',
                        'village_row_id',
                    ]),
                    [
                        'photo_url' => $user->photo_path
                            ? asset('storage/'.ltrim((string) $user->photo_path, '/')).'?v='.($user->updated_at?->timestamp ?? time())
                            : null,
                    ]
                ) : null,
                'permissions' => $this->resolvePermissions($request),
                'nav_map' => config('permissions.nav_map', []),
                'impersonated_by' => $request->session()?->get('impersonated_by'),
                'impersonator_name' => $request->session()?->get('impersonator_name'),
            ],
            'flash' => $this->resolveFlash($request),
            'logoPath' => $this->resolveLogoPath(),
            'assistant' => $this->resolveAssistant($request),
            'tenant' => $this->resolveTenantInfo(),
            'offline_access' => $offlineAccessData,
            'desktop' => [
                'is_desktop' => (bool) config('desktop.enabled', false),
                'app_version' => '1.0.0',
                'server_url' => config('desktop.server.url'),
                'is_offline' => (bool) config('desktop.offline', false),
            ],
        ];
    }

    private function resolvePermissions(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return [];
        }

        if ($user->is_superadmin === true) {
            return ['*'];
        }

        if ($user->is_province_user === true) {
            return ['province.view_reports'];
        }

        if ($user->is_regency_user === true) {
            return ['regency.view_reports'];
        }

        if ($user->is_village_user === true) {
            return [
                'village_user.access',
                'members.view',
                'members.manage',
                'groups.view',
                'groups.manage',
                'loans.view',
                'loans.propose',
                'reports.view',
            ];
        }

        try {
            return app(PermissionChecker::class)->listFor($user);
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveAssistant(Request $request): array
    {
        $enabled = $request->user() !== null
            && app(PermissionChecker::class)->allows($request->user(), 'assistant.use');

        return [
            'enabled' => $enabled,
            'public_url' => $enabled ? url('/') : null,
        ];
    }

    private function resolveFlash(Request $request): array
    {
        $session = $request->hasSession() ? $request->session() : null;
        if ($session === null) {
            return [];
        }

        $flash = [];
        foreach (['success', 'error', 'warning', 'info'] as $key) {
            $value = $session->get($key);
            if ($value !== null) {
                $flash[$key] = $value;
            }
        }

        return $flash;
    }

    private function resolveLogoPath(): ?string
    {
        try {
            $context = app(TenantContext::class);
            if (! $context->isInitialized()) {
                return null;
            }
            $profile = OrganizationProfile::query()->first(['logo_path']);
            $path = $profile?->logo_path;
            if (! is_string($path) || $path === '') {
                return null;
            }

            return asset('storage/'.ltrim($path, '/'));
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveTenantInfo(): ?array
    {
        try {
            $context = app(TenantContext::class);
            if (! $context->isInitialized()) {
                return null;
            }
            $tenant = $context->tenant();

            return [
                'row_id' => $tenant->row_id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'is_training_mode' => (bool) ($tenant->is_training_mode ?? false),
                'training_started_at' => $tenant->training_started_at?->toIso8601String(),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
