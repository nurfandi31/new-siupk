<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;

final class FiscalPeriod extends TenantModel
{
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'closed_at' => 'datetime',
        ];
    }
}
