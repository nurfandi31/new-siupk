<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Role extends TenantModel
{
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_row_id', 'row_id');
    }
}
