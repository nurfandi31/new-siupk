<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Scopes\VillageScope;
use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Member extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;
    use SoftDeletes;

    protected static function booted(): void
    {
        self::addGlobalScope(new VillageScope);
    }

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_row_id', 'row_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_row_id', 'row_id');
    }

    public function address(): HasOne
    {
        return $this->hasOne(MemberAddress::class, 'member_row_id', 'row_id')->where('is_primary', true);
    }

    public function business(): HasOne
    {
        return $this->hasOne(MemberBusiness::class, 'member_row_id', 'row_id')->where('is_active', true);
    }

    public function guarantor(): HasOne
    {
        return $this->hasOne(MemberGuarantor::class, 'member_row_id', 'row_id')->latestOfMany();
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'member_row_id', 'row_id');
    }

    public function groupOffices(): HasMany
    {
        return $this->hasMany(GroupOfficer::class, 'member_row_id', 'row_id');
    }
}
