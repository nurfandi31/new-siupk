<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Scopes\VillageScope;
use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Group extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;
    use SoftDeletes;

    protected $table = 'groups';

    protected static function booted(): void
    {
        self::addGlobalScope(new VillageScope);
    }

    protected function casts(): array
    {
        return ['established_at' => 'date'];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_row_id', 'row_id');
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class, 'business_type_row_id', 'row_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_row_id', 'row_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(GroupLevel::class, 'group_level_row_id', 'row_id');
    }

    public function functionType(): BelongsTo
    {
        return $this->belongsTo(GroupFunction::class, 'group_function_row_id', 'row_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'group_row_id', 'row_id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active')->whereNull('left_at');
    }

    public function officers(): HasMany
    {
        return $this->hasMany(GroupOfficer::class, 'group_row_id', 'row_id');
    }

    public function activeOfficers(): HasMany
    {
        return $this->officers()->whereNull('ended_at');
    }
}
