<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanPaymentAllocation extends TenantModel
{
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LoanPayment::class, 'payment_row_id', 'row_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'installment_row_id', 'row_id');
    }
}
