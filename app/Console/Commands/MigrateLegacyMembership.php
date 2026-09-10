<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Membership\MembershipMigrationPipeline;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

final class MigrateLegacyMembership extends Command
{
    protected $signature = 'legacy:migrate-membership
        {tenant : Tenant row ID or code (e.g. local)}
        {suffix : Legacy lokasi id (e.g. 1 → anggota_1)}
        {--dry-run : Validate only; no writes}
        {--chunk=500 : Extract/load chunk size}
        {--fail-fast : Abort on first invalid row (default true)}
        {--no-fail-fast : Collect all errors}
        {--skip-members : Skip anggota_*}
        {--skip-groups : Skip kelompok_*}
        {--skip-reconcile : Skip recon write}';

    protected $description = 'Migrate legacy anggota_* + kelompok_* into people/members/groups for a Next tenant.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        MembershipMigrationPipeline $pipeline,
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
                'Migrating membership suffix=%s → tenant=%s (%s)%s',
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
                skipMembers: (bool) $this->option('skip-members'),
                skipGroups: (bool) $this->option('skip-groups'),
                skipReconcile: (bool) $this->option('skip-reconcile'),
            );

            $this->line('Source anggota: '.$result['source_members']);
            $this->line('Source kelompok: '.$result['source_groups']);
            $this->line('Would insert members: '.$result['would_insert_members']);
            $this->line('Would skip members: '.$result['would_skip_members']);
            $this->line('Would insert groups: '.$result['would_insert_groups']);
            $this->line('Would skip groups: '.$result['would_skip_groups']);
            $this->line('Inserted members: '.$result['inserted_members']);
            $this->line('Inserted groups: '.$result['inserted_groups']);
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

            $warningCount = (int) ($result['warning_count'] ?? count($result['warnings'] ?? []));
            if ($warningCount > 0) {
                $this->comment("Warnings: {$warningCount} (showing up to 20)");
                foreach (array_slice($result['warnings'] ?? [], 0, 20) as $w) {
                    $this->line('  ~ '.$w);
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
}
