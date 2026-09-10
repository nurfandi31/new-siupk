<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use RuntimeException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?TenantPlacement $placement = null;

    private ?DatabaseShard $shard = null;

    public function initialize(Tenant $tenant, TenantPlacement $placement, DatabaseShard $shard): void
    {
        if ((int) $placement->tenant_id !== (int) $tenant->row_id) {
            throw new RuntimeException('Tenant placement does not belong to the resolved tenant.');
        }

        if ((int) $placement->shard_id !== (int) $shard->row_id) {
            throw new RuntimeException('Resolved shard does not match tenant placement.');
        }

        $this->tenant = $tenant;
        $this->placement = $placement;
        $this->shard = $shard;
    }

    public function isInitialized(): bool
    {
        return $this->tenant !== null && $this->placement !== null && $this->shard !== null;
    }

    public function id(): int
    {
        return (int) $this->tenant()->row_id;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('Tenant context has not been initialized.');
    }

    public function placement(): TenantPlacement
    {
        return $this->placement ?? throw new RuntimeException('Tenant context has not been initialized.');
    }

    public function shard(): DatabaseShard
    {
        return $this->shard ?? throw new RuntimeException('Tenant context has not been initialized.');
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->placement = null;
        $this->shard = null;
    }
}
