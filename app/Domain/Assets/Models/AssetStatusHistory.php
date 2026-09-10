<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetStatusHistory extends TenantModel
{
    use HasTenantLocalId;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_row_id', 'row_id');
    }
}
