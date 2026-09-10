<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanBorrower extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'loan_borrowers';

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_row_id', 'row_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_row_id', 'row_id');
    }
}
