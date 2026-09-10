<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssetCategory extends TenantModel
{
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'default_useful_life_months' => 'integer',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_category_row_id', 'row_id');
    }
}
