<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Accounting\Services\MonthlyBalanceRecalculator;
use App\Jobs\Middleware\InitializeTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecalculateMonthlyBalances implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $year,
        public readonly int $month,
    ) {
        $this->onQueue('accounting');
    }

    public function middleware(): array
    {
        return [new InitializeTenant($this->tenantId)];
    }

    public function handle(MonthlyBalanceRecalculator $recalculator): void
    {
        $recalculator->recalculate($this->year, $this->month);
    }
}
