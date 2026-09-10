<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Tenancy\Concerns\HasTenantLocalId;

final class BusinessType extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'business_types';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
