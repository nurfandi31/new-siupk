<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Lending\LendingMigrationPipeline;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

final class MigrateLegacyLending extends Command
{
    protected $signature = 'legacy:migrate-lending
        {tenant : Tenant row ID or code (e.g. local)}
        {suffix : Legacy lokasi id (e.g. 1)}
        {--dry-run : Validate only; no writes}
        {--chunk=500 : Extract/load chunk size}
        {--fail-fast : Abort on first invalid row (default true)}
        {--no-fail-fast : Collect all errors}
        {--skip-loans : Skip pinjaman_kelompok_*}
        {--skip-beneficiaries : Skip pinjaman_anggota_*}
        {--skip-installments : Skip rencana_angsuran_*}
        {--skip-payments : Skip real_angsuran_*}
        {--skip-reconcile : Skip recon write}';

    protected $description = 'Migrate legacy pinjaman_kelompok/anggota + angsuran into loans for a Next tenant.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        LendingMigrationPipeline $pipeline,
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
                'Migrating lending suffix=%s → tenant=%s (%s)%s',
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
                skipLoans: (bool) $this->option('skip-loans'),
                skipBeneficiaries: (bool) $this->option('skip-beneficiaries'),
                skipInstallments: (bool) $this->option('skip-installments'),
                skipPayments: (bool) $this->option('skip-payments'),
                skipReconcile: (bool) $this->option('skip-reconcile'),
            );

            foreach ([
                'source_loans', 'source_beneficiaries', 'source_installments', 'source_payments',
                'would_insert_loans', 'would_skip_loans',
                'would_insert_beneficiaries', 'would_skip_beneficiaries',
                'would_insert_installments', 'would_skip_installments',
                'would_insert_payments', 'would_skip_payments',
                'inserted_loans', 'inserted_beneficiaries', 'inserted_installments', 'inserted_payments',
            ] as $k) {
                $this->line($k.': '.($result[$k] ?? 0));
            }
            $this->line('Status: '.$result['status']);
            if ($result['batch_row_id'] !== null) {
                $this->line('Batch row_id: '.$result['batch_row_id']);
            }

            $errorCount = (int) ($result['error_count'] ?? 0);
            if ($errorCount > 0) {
                $this->warn("Errors: {$errorCount} (showing up to 50)");
                foreach ($result['errors'] as $err) {
                    $this->line('  - '.$err);
                }
            }
            $warningCount = (int) ($result['warning_count'] ?? 0);
            if ($warningCount > 0) {
                $this->comment("Warnings: {$warningCount} (showing up to 20)");
                foreach (array_slice($result['warnings'] ?? [], 0, 20) as $w) {
                    $this->line('  ~ '.$w);
                }
            }

            if (($result['recon'] ?? []) !== []) {
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
