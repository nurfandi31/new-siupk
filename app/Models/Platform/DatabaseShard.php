<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class DatabaseShard extends PlatformModel
{
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'maximum_weight' => 'integer',
            'current_weight' => 'integer',
        ];
    }

    public function placements(): HasMany
    {
        return $this->hasMany(TenantPlacement::class, 'shard_id', 'row_id');
    }
}
