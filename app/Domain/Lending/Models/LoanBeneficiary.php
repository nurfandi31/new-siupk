<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Domain\Membership\Models\Member;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanBeneficiary extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'loan_beneficiaries';

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'proposed_amount' => 'decimal:2',
            'verified_amount' => 'decimal:2',
            'written_off_amount' => 'decimal:2',
            'written_off_at' => 'datetime',
        ];
    }

    public function isWrittenOff(): bool
    {
        return $this->written_off_at !== null;
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_row_id', 'row_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }
}
