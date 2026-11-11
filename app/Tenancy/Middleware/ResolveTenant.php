<?php

declare(strict_types=1);

namespace App\Tenancy\Middleware;

use App\Tenancy\Services\PlatformHostResolver;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class ResolveTenant
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantContext $context,
        private ShardConnectionManager $connectionManager,
        private PlatformHostResolver $platformHosts,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $parameter = (string) config('tenancy.route_parameter', 'tenant');
        $code = $request->route($parameter);

        if (! is_string($code) || trim($code) === '') {
            // Server-to-server tool callbacks (orchestrator) may send X-Tenant-Code
            // when multiple tenants share one host. Gated by tenancy.allow_header.
            if (config('tenancy.allow_header')) {
                $headerName = (string) config('tenancy.header', 'X-Tenant-Code');
                $headerCode = $request->header($headerName);
                if (is_string($headerCode) && trim($headerCode) !== '') {
                    $code = trim($headerCode);
                }
            }
        }

        // Some routes (e.g. the /login/user quick-login switcher) must always
        // scope to the tenant that owns the current host, even when a user from
        // another tenant happens to be signed in. Those routes set this default.
        $forceHost = (bool) $request->route('tenant_from_host', false);

        if ($forceHost) {
            $tenant = $this->resolver->resolveByHost($request->getHost(), $request->user());
        } elseif (is_string($code) && trim($code) !== '') {
            $trimmed = trim($code);
            // Numeric route param = row_id (e.g. /admin/tenants/{tenant}/onboarding).
            // String route param = tenant code (e.g. /admin/tenants/local/onboarding).
            $tenant = ctype_digit($trimmed)
                ? $this->resolver->resolveById((int) $trimmed, $request->user())
                : $this->resolver->resolveByCode($trimmed, $request->user());
        } elseif ($request->user() !== null && $request->user()->getAttribute('tenant_id') !== null) {
            $this->guardPlatformHost($request);
            $tenant = $this->resolver->resolveForUser($request->user());
        } else {
            $tenant = $this->resolver->resolveByHost($request->getHost(), $request->user());
        }

        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            throw new BadRequestHttpException('Tenant placement is incomplete.');
        }

        $this->connectionManager->connect($shard);
        $this->context->initialize($tenant, $placement, $shard);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
            $this->connectionManager->disconnect();
        }
    }

    private function guardPlatformHost(Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        if ((bool) $user->getAttribute('is_superadmin')
            || (bool) $user->getAttribute('is_regency_user')
            || (bool) $user->getAttribute('is_province_user')) {
            return;
        }

        if ($request->hasSession() && $request->session()->get('impersonated_by') !== null) {
            return;
        }

        if (! $this->platformHosts->isEnforcedPlatformHost($request->getHost())) {
            return;
        }

        throw new AccessDeniedHttpException(
            'Akun tenant tidak dapat mengakses aplikasi tenant melalui domain pusat. Silakan gunakan domain tenant masing-masing.'
        );
    }
}
