<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Accounting\AccountingMigrationPipeline;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

final class MigrateLegacyAccounting extends Command
{
    protected $signature = 'legacy:migrate-accounting
        {tenant : Tenant row ID or code (e.g. local)}
        {suffix : Legacy lokasi id (e.g. 1 → transaksi_1)}
        {--dry-run : Validate only; no writes}
        {--chunk=500 : Extract/load chunk size}
        {--from-date= : Optional YYYY-MM-DD}
        {--to-date= : Optional YYYY-MM-DD}
        {--fail-fast : Abort on first invalid row (default true)}
        {--no-fail-fast : Collect all errors}
        {--skip-openings : Skip saldo bulan=0}
        {--skip-journals : Skip transaksi}
        {--skip-recalc : Skip monthly balance recalc}
        {--skip-reconcile : Skip recon write}';

    protected $description = 'Migrate legacy transaksi_* + saldo bulan=0 into journal_entries for a Next tenant.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        AccountingMigrationPipeline $pipeline,
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
        $fromDate = $this->nullableOption('from-date');
        $toDate = $this->nullableOption('to-date');
        $failFast = ! $this->option('no-fail-fast');

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $this->info(sprintf(
                'Migrating legacy suffix=%s → tenant=%s (%s)%s',
                $suffix,
                $tenant->code,
                $tenant->row_id,
                $dryRun ? ' [DRY-RUN]' : '',
            ));

            $result = $pipeline->run(
                suffix: $suffix,
                dryRun: $dryRun,
                chunk: $chunk,
                fromDate: $fromDate,
                toDate: $toDate,
                failFast: $failFast,
                skipOpenings: (bool) $this->option('skip-openings'),
                skipJournals: (bool) $this->option('skip-journals'),
                skipRecalc: (bool) $this->option('skip-recalc'),
                skipReconcile: (bool) $this->option('skip-reconcile'),
            );

            $this->line('Source active transaksi: '.$result['source_count']);
            $this->line('Would insert journals: '.$result['would_insert_journals']);
            $this->line('Would skip (mapped): '.$result['would_skip_journals']);
            $this->line('Would insert openings: '.$result['would_insert_openings']);
            $this->line('Inserted journals: '.$result['inserted_journals']);
            $this->line('Inserted openings: '.$result['inserted_openings']);
            $this->line('Status: '.$result['status']);
            if ($result['batch_row_id'] !== null) {
                $this->line('Batch row_id: '.$result['batch_row_id']);
            }

            $errorCount = (int) ($result['error_count'] ?? count($result['errors']));
            if ($errorCount > 0) {
                $this->warn("Errors: {$errorCount} (showing up to 50)");
                foreach ($result['errors'] as $err) {
                    $this->line('  - '.$err);
                }
            }

            if ($result['recon'] !== []) {
                $this->newLine();
                $this->table(
                    ['scope', 'status', 'source', 'target'],
                    array_map(static fn (array $r): array => [
                        $r['scope'] ?? '',
                        $r['status'] ?? '',
                        $r['source_count'] ?? '',
                        $r['target_count'] ?? '',
                    ], $result['recon']),
                );
            }

            $ok = in_array($result['status'], ['completed', 'dry_run_ok'], true);

            return $ok ? self::SUCCESS : self::FAILURE;
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
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('code', $value),
            )
            ->firstOrFail();
    }

    private function nullableOption(string $key): ?string
    {
        $v = $this->option($key);

        return is_string($v) && $v !== '' ? $v : null;
    }
}
