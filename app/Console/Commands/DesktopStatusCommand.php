<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class DesktopStatusCommand extends Command
{
    protected $signature = 'desktop:status';

    protected $description = 'Check local SQLite database status and desktop synchronization settings';

    public function handle(): int
    {
        $this->info('=== SIUPK Next Desktop Client Status ===');

        $isDesktop = (bool) config('desktop.enabled', false);
        $sqlitePath = (string) config('desktop.sqlite_database', database_path('database.sqlite'));
        $serverUrl = (string) config('desktop.server.url', 'https://app.siupk.id');
        $tenantCode = (string) config('desktop.server.tenant_code', 'default');

        $this->table(
            ['Configuration', 'Value'],
            [
                ['Desktop Mode Enabled', $isDesktop ? '<info>YES</info>' : '<comment>NO (Web Server Mode)</comment>'],
                ['SQLite Path', $sqlitePath],
                ['SQLite File Exists', File::exists($sqlitePath) ? '<info>YES</info> ('.round(File::size($sqlitePath) / 1024, 2).' KB)' : '<error>NO</error>'],
                ['Sync Server Target', $serverUrl],
                ['Assigned Tenant Code', $tenantCode],
                ['Sync Timeout', config('desktop.server.timeout_seconds', 30).' seconds'],
                ['Auto Sync On Launch', config('desktop.server.auto_sync_on_launch', true) ? 'YES' : 'NO'],
            ]
        );

        if (File::exists($sqlitePath)) {
            try {
                $registryCount = DB::connection('sqlite')->table('tenant_registry')->count();
                $accountsCount = DB::connection('sqlite')->table('accounts')->count();
                $membersCount = DB::connection('sqlite')->table('members')->count();
                $loansCount = DB::connection('sqlite')->table('loans')->count();
                $journalsCount = DB::connection('sqlite')->table('journal_entries')->count();

                $this->newLine();
                $this->info('=== Local SQLite Record Summary ===');
                $this->table(
                    ['Entity', 'Local Count'],
                    [
                        ['Tenant Registry', $registryCount],
                        ['Accounts (COA)', $accountsCount],
                        ['Members', $membersCount],
                        ['Loans', $loansCount],
                        ['Journal Entries', $journalsCount],
                    ]
                );
            } catch (\Throwable $e) {
                $this->warn('Could not query SQLite tables (database may be unmigrated): '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
