<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Platform\CutoverRun;
use App\Services\Admin\TenantCutoverRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunTenantCutoverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes max for large datasets

    public function __construct(
        public readonly CutoverRun $cutoverRun
    ) {}

    public function handle(TenantCutoverRunnerService $service): void
    {
        $service->execute($this->cutoverRun);
    }
}
