<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Sync\Services\DesktopSyncClientService;
use Illuminate\Console\Command;

final class DesktopSyncCommand extends Command
{
    protected $signature = 'desktop:sync 
                            {--tenant= : Tenant code (overrides config)}
                            {--delta : Perform incremental/delta sync instead of full snapshot}
                            {--since= : Custom timestamp (ISO8601) for delta sync}';

    protected $description = 'Pull latest tenant snapshot from cloud server and ingest into local SQLite database';

    public function handle(DesktopSyncClientService $syncClient): int
    {
        $tenant = $this->option('tenant') ?? config('desktop.server.tenant_code');
        $isDelta = (bool) $this->option('delta');
        $since = $this->option('since');

        $this->info("Initiating cloud synchronization for tenant: <comment>{$tenant}</comment>");
        $this->line('Mode: <info>'.($isDelta ? 'DELTA / INCREMENTAL' : 'FULL SNAPSHOT').'</info>');

        try {
            $result = $syncClient->syncFromCloud((string) $tenant, $isDelta, $since ? (string) $since : null);

            $this->newLine();
            $this->info('Synchronization completed successfully!');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Sync Type', strtoupper((string) $result['type'])],
                    ['Total Tables Ingested', $result['total_tables']],
                    ['Total Records Ingested', $result['total_records']],
                    ['Execution Time', $result['elapsed_ms'].' ms'],
                    ['Synced At', $result['synced_at']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Synchronization failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
