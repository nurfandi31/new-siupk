<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Person extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;
    use SoftDeletes;

    protected $table = 'people';

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Member::class, 'person_row_id', 'row_id');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(MemberGuarantor::class, 'guarantor_person_row_id', 'row_id');
    }
}
