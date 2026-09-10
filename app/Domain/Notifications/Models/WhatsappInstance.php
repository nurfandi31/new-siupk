<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;

final class WhatsappInstance extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'instance_name',
        'phone_number',
        'status',
        'is_default',
        'is_active',
        'daily_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'daily_limit' => 'integer',
        ];
    }
}
