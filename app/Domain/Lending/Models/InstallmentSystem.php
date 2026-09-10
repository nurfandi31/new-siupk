<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Builder;

final class InstallmentSystem extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'installment_systems';

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'interval_months' => 'integer',
            'sort_order' => 'integer',
            'principal_grace_months' => 'integer',
            'interest_grace_months' => 'integer',
            'principal_interval_months' => 'integer',
            'interest_interval_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('legacy_id');
    }
}
