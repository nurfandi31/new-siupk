<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Domain\Lending\Models\InstallmentSystem;
use App\Tenancy\TenantContext;
use Database\Seeders\InstallmentSystemSeeder;
use Illuminate\Support\Facades\DB;

final readonly class TenantInstallmentSystemProvisioner
{
    public function ensureDefaults(): void
    {
        $tenantId = app(TenantContext::class)->id();

        DB::connection('tenant')->transaction(function () use ($tenantId): void {
            foreach (InstallmentSystemSeeder::systems() as $system) {
                InstallmentSystem::query()->updateOrCreate([
                    'tenant_id' => $tenantId,
                    'legacy_id' => $system['legacy_id'],
                ], $system);
            }
        });
    }
}
