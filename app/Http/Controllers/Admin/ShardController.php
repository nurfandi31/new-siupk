<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\CutoverRun;
use App\Models\Platform\DatabaseShard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ShardController
{
    /**
     * Read-only overview infrastruktur sharding: daftar shard beserta beban
     * tenant, versi skema, dan riwayat cutover run terakhir. Aksi tulis
     * (provisioning/cutover) tetap di halaman Migrasi Data & CLI artisan.
     */
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        $shards = DatabaseShard::query()
            ->withCount(['placements as tenants_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('code')
            ->get()
            ->map(function (DatabaseShard $shard): array {
                $schemaVersion = DB::connection('platform')
                    ->table('shard_schema_versions')
                    ->where('shard_id', $shard->row_id)
                    ->first();

                return [
                    'row_id' => $shard->row_id,
                    'code' => $shard->code,
                    'name' => $shard->name,
                    'driver' => $shard->driver,
                    'endpoint' => "{$shard->host}:{$shard->port}",
                    'database_name' => $shard->database_name,
                    'placement_type' => $shard->placement_type,
                    'status' => $shard->status,
                    'current_weight' => (int) $shard->current_weight,
                    'maximum_weight' => $shard->maximum_weight !== null ? (int) $shard->maximum_weight : null,
                    'tenants_count' => (int) $shard->tenants_count,
                    'schema_version' => $schemaVersion !== null ? [
                        'current_version' => $schemaVersion->current_version,
                        'target_version' => $schemaVersion->target_version,
                        'status' => $schemaVersion->status,
                    ] : null,
                ];
            });

        $totalTenantsPlaced = $shards->sum('tenants_count');

        $runs = CutoverRun::query()
            ->with(['tenant:row_id,code,name'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CutoverRun $run): array => [
                'id' => $run->id,
                'tenant_code' => $run->tenant_code,
                'tenant_name' => $run->tenant?->name ?? $run->tenant_code,
                'suffix' => $run->suffix,
                'is_dry_run' => (bool) $run->is_dry_run,
                'status' => $run->status,
                'steps_total' => is_array($run->steps) ? count($run->steps) : 0,
                'steps_ok' => is_array($run->steps) ? count(array_filter($run->steps, static fn ($s) => ($s['status'] ?? '') === 'ok')) : 0,
                'error_message' => $run->error_message,
                'started_at' => $run->started_at?->format('d/m/Y H:i'),
                'completed_at' => $run->completed_at?->format('d/m/Y H:i'),
                'created_at' => $run->created_at?->format('d/m/Y H:i'),
            ]);

        $summary = [
            'total_shards' => $shards->count(),
            'active_shards' => $shards->where('status', 'active')->count(),
            'total_tenants_placed' => $totalTenantsPlaced,
            'runs_completed' => CutoverRun::query()->where('status', 'completed')->count(),
            'runs_failed' => CutoverRun::query()->where('status', 'failed')->count(),
            'schema_lagging' => $shards->filter(
                static fn (array $s): bool => $s['schema_version'] !== null
                    && $s['schema_version']['current_version'] !== $s['schema_version']['target_version'],
            )->count(),
        ];

        return Inertia::render('Admin/Shards/Index', [
            'shards' => $shards,
            'runs' => $runs,
            'summary' => $summary,
            'perPage' => $perPage,
        ]);
    }
}
