<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserRole extends TenantModel
{
    use HasTenantLocalId;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_row_id', 'row_id');
    }
}
