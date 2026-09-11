<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\CutoverRun;
use App\Services\Admin\TenantCutoverRunnerService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies that TenantCutoverRunnerService::observeStream() is a PURE observer
 * (F014 fix). For completed runs it emits once and returns. For pending/running
 * runs it polls until terminal (verified manually via the artisan one-liner
 * in docs/audit/2026-08-14/fixes.md F014 — see also `docs/audit/2026-08-14/
 * number-input-and-sse-audit.md` A3).
 */
final class ObserveStreamTest extends TestCase
{
    #[Test]
    public function observer_emits_state_for_completed_run_and_returns(): void
    {
        config(['database.default' => 'platform']);
        DB::purge('platform');

        $run = CutoverRun::query()->create([
            'tenant_id' => 1,
            'tenant_code' => 'local',
            'suffix' => '76',
            'is_dry_run' => false,
            'options' => [],
            'status' => 'completed',
        ]);

        $events = [];
        $svc = app(TenantCutoverRunnerService::class);
        $svc->observeStream($run, function (string $event, array $data) use (&$events) {
            $events[] = ['event' => $event, 'status' => $data['status'] ?? null];
        });

        $this->assertCount(1, $events, 'Completed run: emits exactly one event then returns.');
        $this->assertSame('update', $events[0]['event']);
        $this->assertSame('completed', $events[0]['status']);
    }

    #[Test]
    public function observer_renamed_from_execute_stream_and_writer_remains(): void
    {
        // Critical structural assertion — if executeStream comes back, this fails.
        $methods = get_class_methods(TenantCutoverRunnerService::class);
        $this->assertContains('observeStream', $methods, 'observeStream MUST exist (F014).');
        $this->assertNotContains('executeStream', $methods, 'OLD executeStream MUST be removed (was unsafe — re-ran cutover).');
        $this->assertContains('execute', $methods, 'execute() writer MUST remain (queue worker + run_immediately path).');
    }
}
