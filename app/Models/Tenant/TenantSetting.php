<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

final class TenantSetting extends Model
{
    protected $connection = 'tenant';

    protected $table = 'tenant_settings';

    protected $primaryKey = 'row_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }
}
