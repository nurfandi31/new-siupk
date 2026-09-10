<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Models;

use App\Domain\Accounting\Models\Account;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BudgetLine extends TenantModel
{
    use HasTenantLocalId;

    protected function casts(): array
    {
        return [
            'fiscal_month' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_row_id', 'row_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_row_id', 'row_id');
    }
}
