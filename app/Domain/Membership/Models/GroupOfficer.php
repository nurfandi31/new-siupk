<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GroupOfficer extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'group_officers';

    protected function casts(): array
    {
        return ['started_at' => 'date', 'ended_at' => 'date'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_row_id', 'row_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_row_id', 'row_id');
    }
}
