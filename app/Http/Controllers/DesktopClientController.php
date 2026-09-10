<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Sync\Services\DesktopOutboxService;
use App\Domain\Sync\Services\DesktopSyncClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DesktopClientController
{
    public function __construct(
        private readonly DesktopSyncClientService $syncClient,
        private readonly DesktopOutboxService $outboxService,
    ) {}

    /**
     * Get desktop client status, connection info, and last sync timestamp.
     */
    public function status(): JsonResponse
    {
        $lastSync = $this->syncClient->getLastSyncInfo();
        $ping = $this->syncClient->pingServer();
        $outbox = $this->outboxService->stats();

        return response()->json([
            'status' => 'success',
            'is_desktop' => (bool) config('desktop.enabled', false),
            'server_target' => config('desktop.server.url'),
            'server_online' => $ping['online'] ?? false,
            'server_latency_ms' => $ping['latency_ms'] ?? null,
            'tenant' => $lastSync ? [
                'id' => $lastSync['tenant_id'],
                'code' => $lastSync['tenant_code'],
                'name' => $lastSync['tenant_name'],
            ] : null,
            'synced_at' => $lastSync['synced_at'] ?? null,
            'outbox' => $outbox,
        ]);
    }

    /**
     * Trigger manual pull sync from cloud server.
     */
    public function triggerSync(Request $request): JsonResponse
    {
        $tenant = $request->input('tenant') ?? config('desktop.server.tenant_code');
        $isDelta = (bool) $request->input('delta', false);
        $since = $request->input('since');

        try {
            $push = $this->outboxService->flushPendingMutations($tenant ? (string) $tenant : null);
            $result = $this->syncClient->syncFromCloud(
                $tenant ? (string) $tenant : null,
                $isDelta,
                $since ? (string) $since : null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Data successfully synchronized from cloud server.',
                'details' => $result,
                'push' => $push,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
