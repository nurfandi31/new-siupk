<?php

declare(strict_types=1);

namespace App\Models\Platform;

final class WhatsappPlatformInstance extends PlatformModel
{
    protected $table = 'whatsapp_platform_instances';

    protected $fillable = [
        'name',
        'instance_name',
        'phone',
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
