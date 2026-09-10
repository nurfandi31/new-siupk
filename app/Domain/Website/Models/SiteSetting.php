<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use App\Domain\Sync\Contracts\ExcludedFromDesktopSync;
use App\Models\Tenant\TenantModel;

final class SiteSetting extends TenantModel implements ExcludedFromDesktopSync
{
    protected $table = 'site_settings';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
