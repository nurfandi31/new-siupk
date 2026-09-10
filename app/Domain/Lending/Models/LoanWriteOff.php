<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanWriteOff extends TenantModel
{
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'principal_balance' => 'decimal:2',
            'interest_balance' => 'decimal:2',
            'written_off_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_row_id', 'row_id');
    }
}
