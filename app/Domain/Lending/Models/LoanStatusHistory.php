<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Models\Tenant\TenantModel;
use App\Models\User;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanStatusHistory extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'loan_status_histories';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'principal_amount' => 'decimal:2',
            'service_rate_total' => 'decimal:4',
            'term_months' => 'integer',
            'principal_grace_months' => 'integer',
            'interest_grace_months' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_row_id', 'row_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id', 'row_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id', 'row_id');
    }
}
