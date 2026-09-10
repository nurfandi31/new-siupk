<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VillageNaming extends TenantModel
{
    protected $table = 'village_namings';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function villages(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'village_naming_id', 'row_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
