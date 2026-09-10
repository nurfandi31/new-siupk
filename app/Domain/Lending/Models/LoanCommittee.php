<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Domain\Membership\Models\Member;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanCommittee extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'loan_committee';

    protected function casts(): array
    {
        return ['snapshot_at' => 'date'];
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
