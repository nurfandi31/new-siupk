<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Phase 5 rehearsal: ordered legacy → Next load for one tenant + suffix.
 *
 * @see docs/CUTOVER_RUNBOOK.md
 */
final class CutoverTenant extends Command
{
    protected $signature = 'legacy:cutover-tenant
        {tenant : Tenant code or row id}
        {suffix : Legacy lokasi id}
        {--dry-run : Pass --dry-run to migrate steps (no writes on those steps)}
        {--chunk=500 : Chunk size for migrate commands}
        {--from-year=2018 : Fiscal ensure from year}
        {--to-year= : Fiscal ensure to year (default current)}
        {--no-fail-fast : Pass --no-fail-fast to migrate commands}
        {--skip-fiscal : Skip legacy:ensure-fiscal-periods}
        {--skip-coa : Skip tenancy:import-legacy-chart-of-accounts}
        {--skip-accounting : Skip legacy:migrate-accounting}
        {--skip-membership : Skip legacy:migrate-membership}
        {--skip-lending : Skip legacy:migrate-lending}
        {--skip-payment-progress : Skip legacy:apply-loan-payment-progress}
        {--skip-reconcile : Skip legacy:reconcile-lending}
        {--skip-sequences : Skip tenancy:initialize-sequences}
        {--continue-on-error : Do not abort chain on first non-zero exit}';

    protected $description = 'Run ordered cutover chain for one tenant+suffix (see docs/CUTOVER_RUNBOOK.md).';

    public function handle(): int
    {
        $tenantArg = (string) $this->argument('tenant');
        $suffix = (string) $this->argument('suffix');
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix must be numeric.');
        }

        $tenant = Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($tenantArg),
                fn ($q) => $q->whereKey((int) $tenantArg),
                fn ($q) => $q->where('code', $tenantArg),
            )
            ->firstOrFail();

        if ($tenant->placement === null || $tenant->placement->shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $noFailFast = (bool) $this->option('no-fail-fast');
        $continue = (bool) $this->option('continue-on-error');
        $fromYear = (int) $this->option('from-year');
        $toYearOpt = $this->option('to-year');
        $toYear = is_string($toYearOpt) && $toYearOpt !== ''
            ? (int) $toYearOpt
            : (int) date('Y');

        $this->info(sprintf(
            'Cutover chain tenant=%s (%s) suffix=%s%s',
            $tenant->code,
            $tenant->row_id,
            $suffix,
            $dryRun ? ' [DRY-RUN migrates]' : '',
        ));
        $this->line('Docs: docs/CUTOVER_RUNBOOK.md');
        $this->newLine();

        $steps = $this->buildSteps(
            tenant: (string) $tenant->code,
            suffix: $suffix,
            dryRun: $dryRun,
            chunk: $chunk,
            noFailFast: $noFailFast,
            fromYear: $fromYear,
            toYear: $toYear,
        );

        $results = [];
        $failed = false;

        foreach ($steps as $step) {
            if ($step['skip']) {
                $this->comment("SKIP  {$step['name']}");
                $results[] = ['step' => $step['name'], 'status' => 'skipped', 'exit' => null];

                continue;
            }

            $this->info(">>> {$step['name']}");
            $this->line('    '.implode(' ', array_merge([$step['command']], $this->flattenArgs($step['params']))));

            $exit = Artisan::call($step['command'], $step['params']);
            $out = trim(Artisan::output());
            if ($out !== '') {
                $this->output->writeln($this->indent($out));
            }

            $ok = $exit === self::SUCCESS;
            $results[] = [
                'step' => $step['name'],
                'status' => $ok ? 'ok' : 'failed',
                'exit' => $exit,
            ];

            if (! $ok) {
                $this->error("<<< FAILED exit={$exit} — {$step['name']}");
                $failed = true;
                if (! $continue) {
                    break;
                }
            } else {
                $this->info("<<< OK {$step['name']}");
            }
            $this->newLine();
        }

        $this->table(
            ['step', 'status', 'exit'],
            array_map(static fn (array $r): array => [
                $r['step'],
                $r['status'],
                $r['exit'] ?? '—',
            ], $results),
        );

        if ($failed) {
            $this->error('Cutover chain finished with failures. See docs/CUTOVER_RUNBOOK.md acceptance.');

            return self::FAILURE;
        }

        $this->info('Cutover chain completed. Run smoke UI + laporan; review recon exceptions.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, command: string, params: array<string, mixed>, skip: bool}>
     */
    private function buildSteps(
        string $tenant,
        string $suffix,
        bool $dryRun,
        int $chunk,
        bool $noFailFast,
        int $fromYear,
        int $toYear,
    ): array {
        $commonFlags = [
            '--chunk' => $chunk,
        ];
        if ($dryRun) {
            $commonFlags['--dry-run'] = true;
        }
        if ($noFailFast) {
            $commonFlags['--no-fail-fast'] = true;
        }

        $accountingFlags = $commonFlags;
        if ($fromYear > 0) {
            $accountingFlags['--from-date'] = sprintf('%04d-01-01', $fromYear);
        }
        if ($toYear > 0) {
            $accountingFlags['--to-date'] = sprintf('%04d-12-31', $toYear);
        }

        return [
            [
                'name' => 'fiscal-periods',
                'command' => 'legacy:ensure-fiscal-periods',
                'params' => [
                    'tenant' => $tenant,
                    '--from' => $fromYear,
                    '--to' => $toYear,
                ],
                'skip' => (bool) $this->option('skip-fiscal') || $dryRun,
            ],
            [
                'name' => 'chart-of-accounts',
                'command' => 'tenancy:import-legacy-chart-of-accounts',
                'params' => ['tenant' => $tenant],
                'skip' => (bool) $this->option('skip-coa') || $dryRun,
            ],
            [
                'name' => 'accounting',
                'command' => 'legacy:migrate-accounting',
                'params' => array_merge([
                    'tenant' => $tenant,
                    'suffix' => $suffix,
                ], $accountingFlags),
                'skip' => (bool) $this->option('skip-accounting'),
            ],
            [
                'name' => 'membership',
                'command' => 'legacy:migrate-membership',
                'params' => array_merge([
                    'tenant' => $tenant,
                    'suffix' => $suffix,
                ], $commonFlags),
                'skip' => (bool) $this->option('skip-membership'),
            ],
            [
                'name' => 'lending',
                'command' => 'legacy:migrate-lending',
                'params' => array_merge([
                    'tenant' => $tenant,
                    'suffix' => $suffix,
                ], $commonFlags),
                'skip' => (bool) $this->option('skip-lending'),
            ],
            [
                'name' => 'loan-payment-progress',
                'command' => 'legacy:apply-loan-payment-progress',
                'params' => ['tenant' => $tenant],
                'skip' => (bool) $this->option('skip-payment-progress') || $dryRun,
            ],
            [
                'name' => 'reconcile-lending',
                'command' => 'legacy:reconcile-lending',
                'params' => [
                    'tenant' => $tenant,
                    'suffix' => $suffix,
                ],
                // Recon always writes result rows; skip on pure dry-run chain.
                'skip' => (bool) $this->option('skip-reconcile') || $dryRun,
            ],
            [
                'name' => 'initialize-sequences',
                'command' => 'tenancy:initialize-sequences',
                'params' => ['tenant' => $tenant],
                'skip' => (bool) $this->option('skip-sequences') || $dryRun,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    private function flattenArgs(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $out[] = (string) $value;

                continue;
            }
            if ($value === true) {
                $out[] = (string) $key;

                continue;
            }
            if ($value === false || $value === null) {
                continue;
            }
            if (! str_starts_with((string) $key, '-')) {
                $out[] = (string) $value;

                continue;
            }
            $out[] = (string) $key.'='.(string) $value;
        }

        return $out;
    }

    private function indent(string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];

        return implode("\n", array_map(
            static fn (string $line): string => '    '.$line,
            $lines,
        ));
    }
}
