<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Tenancy\Concerns\HasTenantLocalId;

final class GroupLevel extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'group_levels';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
