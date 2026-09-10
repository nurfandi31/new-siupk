<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantPlacement extends PlatformModel
{
    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'moved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'row_id');
    }

    public function shard(): BelongsTo
    {
        return $this->belongsTo(DatabaseShard::class, 'shard_id', 'row_id');
    }
}
