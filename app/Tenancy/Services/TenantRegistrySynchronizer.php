<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Models\Platform\Tenant;
use Illuminate\Support\Facades\DB;

final class TenantRegistrySynchronizer
{
    public function sync(Tenant $tenant, ?string $schemaVersion = null): void
    {
        DB::connection((string) config('tenancy.tenant_connection', 'tenant'))
            ->table('tenant_registry')
            ->updateOrInsert(
                ['id' => (int) $tenant->row_id],
                [
                    'public_id' => (string) $tenant->public_id,
                    'code' => (string) $tenant->code,
                    'name' => (string) $tenant->name,
                    'district_code' => $tenant->district_code,
                    'map_latitude' => $tenant->map_latitude,
                    'map_longitude' => $tenant->map_longitude,
                    'map_zoom' => $tenant->map_zoom,
                    'status' => (string) $tenant->status,
                    'schema_version' => $schemaVersion,
                    'synced_at' => now(),
                    'created_at' => $tenant->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );
    }
}
