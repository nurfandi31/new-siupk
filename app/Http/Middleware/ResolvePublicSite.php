<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Services\PublicSiteResolver;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant that owns the request host for public site rendering.
 *
 * Unlike ResolveTenant this never fails hard on an unknown host — the public
 * site must degrade to the vendor landing page, not a 403. When a tenant host
 * matches, the shard is connected and TenantContext initialized so the
 * controller can read tenant data; context and connection are always released
 * in a finally block (TenantContext is a per-worker singleton, and an uncleared
 * context would leak tenant identity into the next request).
 */
final readonly class ResolvePublicSite
{
    public function __construct(
        private PublicSiteResolver $resolver,
        private TenantContext $context,
        private ShardConnectionManager $connections,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tenant = $this->resolver->resolve($request->getHost());

            if ($tenant !== null && $tenant->placement !== null && $tenant->placement->shard !== null) {
                $this->connections->connect($tenant->placement->shard);
                $this->context->initialize($tenant, $tenant->placement, $tenant->placement->shard);
            }

            return $next($request);
        } finally {
            $this->context->clear();
            $this->connections->disconnect();
        }
    }
}
