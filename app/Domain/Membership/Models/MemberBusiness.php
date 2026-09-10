<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberBusiness extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'member_businesses';

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }

    protected function tenantSequenceName(): string
    {
        return 'member_businesses';
    }
}
