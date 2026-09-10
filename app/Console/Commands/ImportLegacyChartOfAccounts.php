<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\TenantWorkbench;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

final class ImportLegacyChartOfAccounts extends Command
{
    protected $signature = 'tenancy:import-legacy-chart-of-accounts
        {tenant : Tenant row ID or code}
        {--dry-run : Show what would be imported without writing}
        {--reset : Wipe existing accounts for the tenant before importing (DANGEROUS)}
        {--skip-settings : Skip seeding default tenant settings (account.pencairan_*)}';

    protected $description = 'Import the legacy chart-of-accounts into the accounts table for the given tenant.';

    public function handle(TenantWorkbench $workbench, DefaultChartOfAccountsProvisioner $coa): int
    {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));

        try {
            $workbench->run($tenant, function () use ($coa): void {
                if ($this->option('dry-run')) {
                    $preview = $coa->preview();
                    $this->info("Would insert: {$preview['would_insert']}");
                    $this->info("Would skip (existing): {$preview['would_skip']}");

                    return;
                }

                if ($this->option('reset')) {
                    $tenantId = app(TenantContext::class)->id();
                    if (! $this->confirm("Wipe all existing accounts for tenant {$tenantId}?", false)) {
                        $this->info('Aborted by user.');

                        return;
                    }
                    $deleted = $coa->reset();
                    $this->warn("Deleted {$deleted} existing accounts.");
                }

                $result = $coa->ensureDefaults(seedSettings: ! $this->option('skip-settings'));
                $this->info("Inserted: {$result['inserted']}");
                $this->info("Skipped (existing): {$result['skipped']}");
                $this->info("Settings seeded: {$result['settings_seeded']}");
            });
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveTenant(string $value): Tenant
    {
        return Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('code', $value),
            )
            ->firstOrFail();
    }
}
