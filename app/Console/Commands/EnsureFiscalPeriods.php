<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Accounting\Models\FiscalPeriod;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

final class EnsureFiscalPeriods extends Command
{
    protected $signature = 'legacy:ensure-fiscal-periods
        {tenant : Tenant row ID or code}
        {--from=2018 : First fiscal year}
        {--to= : Last fiscal year (default: current)}
        {--status=open : Period status}';

    protected $description = 'Create missing monthly fiscal periods for a year range (migration preflight).';

    public function handle(TenantContext $context, ShardConnectionManager $connections): int
    {
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

        $from = (int) $this->option('from');
        $toOpt = $this->option('to');
        $to = is_string($toOpt) && $toOpt !== '' ? (int) $toOpt : (int) CarbonImmutable::now()->year;
        $status = (string) $this->option('status');

        if ($from > $to || $from < 2000 || $to > 2100) {
            throw new RuntimeException('Invalid year range.');
        }

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $created = 0;
            for ($year = $from; $year <= $to; $year++) {
                for ($month = 1; $month <= 12; $month++) {
                    $exists = FiscalPeriod::query()
                        ->where('fiscal_year', $year)
                        ->where('fiscal_month', $month)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    $starts = CarbonImmutable::create($year, $month, 1)->startOfMonth();
                    FiscalPeriod::query()->create([
                        'fiscal_year' => $year,
                        'fiscal_month' => $month,
                        'starts_at' => $starts->toDateString(),
                        'ends_at' => $starts->endOfMonth()->toDateString(),
                        'status' => $status,
                    ]);
                    $created++;
                }
            }
            $this->info("Created {$created} fiscal period(s) for {$from}–{$to} on tenant [{$tenant->code}].");

            return self::SUCCESS;
        } finally {
            $context->clear();
            $connections->disconnect();
        }
    }
}
