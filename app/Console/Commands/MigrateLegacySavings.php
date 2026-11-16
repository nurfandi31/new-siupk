<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Savings\SavingsMigrationPipeline;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

final class MigrateLegacySavings extends Command
{
    protected $signature = 'legacy:migrate-savings
        {tenant : Tenant row ID or code (e.g. local)}
        {suffix : Legacy lokasi id (e.g. 1)}
        {--dry-run : Validate only; no writes}
        {--chunk=500 : Extract/load chunk size}
        {--no-fail-fast : Collect all errors}
        {--skip-accounts : Skip simpanan_anggota_*}
        {--skip-transactions : Skip real_simpanan_*}
        {--skip-reconcile : Skip recon write}';

    protected $description = 'Migrate legacy simpanan_anggota/real_simpanan into Next savings module tables.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        SavingsMigrationPipeline $pipeline,
    ): int {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        $placement = $tenant->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $suffix = (string) $this->argument('suffix');
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $failFast = ! $this->option('no-fail-fast');

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $this->info(sprintf(
                'Migrating savings suffix=%s → tenant=%s (%s)%s',
                $suffix,
                $tenant->code,
                $tenant->row_id,
                $dryRun ? ' [DRY-RUN]' : '',
            ));

            $result = $pipeline->run(
                suffix: $suffix,
                dryRun: $dryRun,
                chunk: $chunk,
                failFast: $failFast,
                skipAccounts: (bool) $this->option('skip-accounts'),
                skipTransactions: (bool) $this->option('skip-transactions'),
                skipReconcile: (bool) $this->option('skip-reconcile'),
            );

            foreach ([
                'source_products', 'source_accounts', 'source_transactions',
                'would_insert_accounts', 'would_skip_accounts',
                'would_insert_transactions', 'would_skip_transactions',
                'inserted_accounts', 'inserted_transactions',
                'opening_transactions', 'recalculated_accounts',
            ] as $key) {
                $this->line($key.': '.($result[$key] ?? 0));
            }
            $this->line('Status: '.$result['status']);
            if ($result['batch_row_id'] !== null) {
                $this->line('Batch row_id: '.$result['batch_row_id']);
            }

            $errorCount = (int) ($result['error_count'] ?? 0);
            if ($errorCount > 0) {
                $this->warn("Errors: {$errorCount} (showing up to 50)");
                foreach ($result['errors'] as $error) {
                    $this->line('  - '.$error);
                }
            }

            $warningCount = (int) ($result['warning_count'] ?? 0);
            if ($warningCount > 0) {
                $this->comment("Warnings: {$warningCount} (showing up to 20)");
                foreach (array_slice($result['warnings'] ?? [], 0, 20) as $warning) {
                    $this->line('  ~ '.$warning);
                }
            }

            if (($result['recon'] ?? []) !== []) {
                $this->newLine();
                $this->table(
                    ['scope', 'status', 'source', 'target'],
                    array_map(static fn (array $row): array => [
                        $row['scope'] ?? '',
                        $row['status'] ?? '',
                        $row['source_count'] ?? '',
                        $row['target_count'] ?? '',
                    ], $result['recon']),
                );
            }

            return in_array($result['status'], ['completed', 'dry_run_ok'], true)
                ? self::SUCCESS
                : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $context->clear();
            $connections->disconnect();
        }
    }

    private function resolveTenant(string $value): Tenant
    {
        return Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($value),
                fn ($q) => $q->whereKey((int) $value),
                fn ($q) => $q->where('code', $value),
            )
            ->firstOrFail();
    }
}
