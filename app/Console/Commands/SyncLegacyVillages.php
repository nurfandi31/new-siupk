<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Membership\LegacyVillageProvisioner;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Seed + backfill villages from legacy desa codes used by a tenant suffix.
 * Handles custom desa (not only BPS/API list).
 */
final class SyncLegacyVillages extends Command
{
    protected $signature = 'legacy:sync-villages
        {tenant : Tenant code or id}
        {suffix : Legacy lokasi id}
        {--no-backfill : Only create units; do not patch groups/members}';

    protected $description = 'Create organization_units from legacy desa codes used by kelompok/anggota, including custom villages; backfill FKs.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        LegacyVillageProvisioner $provisioner,
    ): int {
        $tenant = Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit((string) $this->argument('tenant')),
                fn ($q) => $q->whereKey((int) $this->argument('tenant')),
                fn ($q) => $q->where('code', (string) $this->argument('tenant')),
            )
            ->firstOrFail();

        $placement = $tenant->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $suffix = (string) $this->argument('suffix');
        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $result = $provisioner->sync(
                suffix: $suffix,
                backfill: ! $this->option('no-backfill'),
            );
        } finally {
            $context->clear();
            $connections->disconnect();
        }

        $this->table(array_keys($result), [array_values($result)]);
        $this->info(sprintf(
            'Villages synced for tenant=%s suffix=%s (created=%d, groups linked=%d, members linked=%d)',
            $tenant->code,
            $suffix,
            $result['created'],
            $result['linked_groups'],
            $result['linked_members'],
        ));

        return self::SUCCESS;
    }
}
