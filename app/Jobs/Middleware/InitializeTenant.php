<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use RuntimeException;

final readonly class InitializeTenant
{
    public function __construct(
        private int $tenantId,
    ) {}

    public function handle(object $job, Closure $next): void
    {
        $resolver = app(TenantResolver::class);
        $context = app(TenantContext::class);
        $connectionManager = app(ShardConnectionManager::class);

        $tenant = $resolver->resolveById($this->tenantId);
        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $connectionManager->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $next($job);
        } finally {
            $context->clear();
            $connectionManager->disconnect();
        }
    }
}
