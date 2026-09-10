<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Services\PhoneNormalizer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $connection = 'platform';

    protected $primaryKey = 'row_id';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_superadmin' => 'boolean',
            'is_regency_user' => 'boolean',
            'is_province_user' => 'boolean',
            'is_village_user' => 'boolean',
            'village_row_id' => 'integer',
            'birth_date' => 'date',
            'appointed_at' => 'date',
            'term_end_at' => 'date',
            'notifications_read' => 'array',
        ];
    }

    public function normalizePhone(): string
    {
        return app(PhoneNormalizer::class)->normalize((string) $this->phone);
    }

    public function isRegencyUser(): bool
    {
        return $this->is_regency_user === true;
    }

    public function isProvinceUser(): bool
    {
        return $this->is_province_user === true;
    }

    public function isVillageUser(): bool
    {
        return $this->is_village_user === true;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'row_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id', 'row_id');
    }
}
