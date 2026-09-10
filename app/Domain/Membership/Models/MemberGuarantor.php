<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberGuarantor extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'member_guarantors';

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'guarantor_person_row_id', 'row_id');
    }

    protected function tenantSequenceName(): string
    {
        return 'member_guarantors';
    }
}
