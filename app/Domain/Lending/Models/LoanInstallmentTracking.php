<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Membership\Models\Member;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanInstallmentTracking extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'loan_installment_tracking';

    protected function casts(): array
    {
        return [
            'principal_paid' => 'decimal:2',
            'interest_paid' => 'decimal:2',
            'penalty_paid' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_row_id', 'row_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_row_id', 'row_id');
    }
}
