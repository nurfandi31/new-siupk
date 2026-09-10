<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Domain\Accounting\Models\FiscalPeriod;
use Carbon\CarbonImmutable;

/** Idempotent monthly fiscal periods for the current (and optional future) years. */
final class FiscalPeriodProvisioner
{
    public function ensureDefaults(int $years = 1, string $status = 'open'): int
    {
        $years = max(1, min($years, 5));
        $startYear = (int) CarbonImmutable::now()->year;
        $created = 0;

        for ($offset = 0; $offset < $years; $offset++) {
            $year = $startYear + $offset;
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

        return $created;
    }
}
