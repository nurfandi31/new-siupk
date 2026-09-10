<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantRegistrySynchronizer;
use Illuminate\Console\Command;
use Throwable;

final class SyncTenantRegistry extends Command
{
    protected $signature = 'tenancy:sync-registry
        {--shard= : Sync only one shard code}
        {--tenant= : Sync only one tenant code}';

    protected $description = 'Synchronize platform tenant metadata into each shard tenant_registry table.';

    public function handle(
        ShardConnectionManager $connections,
        TenantRegistrySynchronizer $registry,
    ): int {
        $shards = DatabaseShard::query()
            ->where('status', 'active')
            ->when(
                is_string($this->option('shard')) && $this->option('shard') !== '',
                fn ($query) => $query->where('code', $this->option('shard')),
            )
            ->orderBy('code')
            ->get();

        $failed = false;

        foreach ($shards as $shard) {
            $this->info("Syncing registry for shard [{$shard->code}]...");

            try {
                $connections->connect($shard);

                Tenant::query()
                    ->whereHas('placement', fn ($query) => $query->where('shard_id', $shard->row_id))
                    ->when(
                        is_string($this->option('tenant')) && $this->option('tenant') !== '',
                        fn ($query) => $query->where('code', $this->option('tenant')),
                    )
                    ->orderBy('row_id')
                    ->chunkById(100, function ($tenants) use ($registry): void {
                        foreach ($tenants as $tenant) {
                            $registry->sync($tenant);
                            $this->line("  synced {$tenant->code}");
                        }
                    }, 'row_id', 'row_id');
            } catch (Throwable $exception) {
                $failed = true;
                report($exception);
                $this->error("Shard [{$shard->code}] failed: {$exception->getMessage()}");
            } finally {
                $connections->disconnect();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
