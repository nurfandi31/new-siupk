<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LoanProduct extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;

    protected $table = 'loan_products';

    protected function casts(): array
    {
        return [
            'default_interest_rate' => 'decimal:4',
            'default_term_months' => 'integer',
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'loan_product_row_id', 'row_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
