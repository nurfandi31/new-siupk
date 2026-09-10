<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Tenancy\Concerns\HasTenantLocalId;

final class GroupFunction extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'group_functions';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
