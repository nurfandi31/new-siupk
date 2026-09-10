<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberAddress extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'member_addresses';

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }

    protected function tenantSequenceName(): string
    {
        return 'member_addresses';
    }
}
