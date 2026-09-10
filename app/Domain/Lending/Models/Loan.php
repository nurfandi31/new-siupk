<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Loan extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;

    protected function tenantSequenceName(): string
    {
        $source = (string) ($this->legacy_source ?: 'group_loan');

        return "loans:{$source}";
    }

    protected function casts(): array
    {
        return [
            'proposed_at' => 'date',
            'verified_at' => 'date',
            'approved_at' => 'date',
            'funded_at' => 'date',
            'disbursed_at' => 'date',
            'completed_at' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'service_rate_total' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_row_id', 'row_id');
    }

    public function borrower(): HasOne
    {
        return $this->hasOne(LoanBorrower::class, 'loan_row_id', 'row_id');
    }

    public function committee(): HasMany
    {
        return $this->hasMany(LoanCommittee::class, 'loan_row_id', 'row_id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(LoanBeneficiary::class, 'loan_row_id', 'row_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LoanStatusHistory::class, 'loan_row_id', 'row_id')->orderByDesc('changed_at');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class, 'loan_row_id', 'row_id')
            ->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'loan_row_id', 'row_id')
            ->orderBy('paid_at');
    }

    public function writeOffs(): HasMany
    {
        return $this->hasMany(LoanWriteOff::class, 'loan_row_id', 'row_id');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_loan_row_id', 'row_id');
    }
}
