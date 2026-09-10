<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Models\Platform\Tenant;
use App\Tenancy\TenantContext;
use RuntimeException;

/**
 * Run a callback with shard connection + TenantContext bound.
 * For admin/CLI paths that are not behind the HTTP `tenant` middleware.
 */
final readonly class TenantWorkbench
{
    public function __construct(
        private TenantContext $context,
        private ShardConnectionManager $connections,
    ) {}

    /**
     * @template T
     *
     * @param  callable(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $tenant->loadMissing('placement.shard');
        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            throw new RuntimeException("Tenant [{$tenant->code}] placement is incomplete.");
        }

        $this->connections->connect($shard);
        $this->context->initialize($tenant, $placement, $shard);

        try {
            return $callback($tenant);
        } finally {
            $this->context->clear();
            $this->connections->disconnect();
        }
    }
}
