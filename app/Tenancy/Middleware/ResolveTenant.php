<?php

declare(strict_types=1);

namespace App\Tenancy\Middleware;

use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class ResolveTenant
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantContext $context,
        private ShardConnectionManager $connectionManager,
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

        if (is_string($code) && trim($code) !== '') {
            $trimmed = trim($code);
            // Numeric route param = row_id (e.g. /admin/tenants/{tenant}/onboarding).
            // String route param = tenant code (e.g. /admin/tenants/local/onboarding).
            $tenant = ctype_digit($trimmed)
                ? $this->resolver->resolveById((int) $trimmed, $request->user())
                : $this->resolver->resolveByCode($trimmed, $request->user());
        } elseif ($request->user() !== null && $request->user()->getAttribute('tenant_id') !== null) {
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
}
