<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Asset extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;
    use SoftDeletes;

    public const STATUSES = [
        'good' => 'Baik',
        'damaged' => 'Rusak',
        'lost' => 'Hilang',
        'sold' => 'Terjual',
        'written_off' => 'Dihapus',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'validated_at' => 'date',
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'useful_life_months' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_row_id', 'row_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_row_id', 'row_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class, 'asset_row_id', 'row_id');
    }
}
