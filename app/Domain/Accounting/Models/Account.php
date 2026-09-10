<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Account extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
            'deactivated_at' => 'date',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_row_id', 'row_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_row_id', 'row_id');
    }
}
