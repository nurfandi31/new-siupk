<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrganizationUnit extends TenantModel
{
    protected $table = 'organization_units';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_row_id', 'row_id');
    }

    public function villageNaming(): BelongsTo
    {
        return $this->belongsTo(VillageNaming::class, 'village_naming_id', 'row_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_row_id', 'row_id');
    }

    public function scopeVillages(Builder $query): Builder
    {
        return $query->where('type', 'village');
    }

    public function scopeOtherInstitutions(Builder $query): Builder
    {
        return $query->where('type', 'other_institution');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
