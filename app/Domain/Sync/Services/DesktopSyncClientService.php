<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class DesktopSyncClientService
{
    public function __construct(
        private readonly DesktopSnapshotIngestionService $ingestionService,
        private readonly DesktopOutboxService $outboxService,
    ) {}

    /**
     * Fetch snapshot from cloud server and ingest into local SQLite database.
     *
     * @return array<string, mixed>
     */
    public function syncFromCloud(?string $tenantCode = null, bool $isDelta = false, ?string $since = null): array
    {
        $subscriptionGate = $this->getSubscriptionGate($tenantCode);

        if (($subscriptionGate['subscription']['blocked'] ?? false) === true) {
            return [
                'status' => 'blocked',
                'blocked' => true,
                'reason' => $subscriptionGate['subscription']['reason'] ?? null,
                'message' => $subscriptionGate['subscription']['message'] ?? null,
                'invoice_number' => $subscriptionGate['subscription']['invoice_number'] ?? null,
            ];
        }

        $pushResult = $this->outboxService->flushPendingMutations($tenantCode);
        $serverUrl = rtrim((string) config('desktop.server.url', 'https://app.siupk.id'), '/');
        $apiKey = (string) config('desktop.server.api_key', '');
        $targetTenant = $tenantCode ?? (string) config('desktop.server.tenant_code', 'default');
        $timeout = (int) config('desktop.server.timeout_seconds', 30);

        if (trim($targetTenant) === '') {
            throw new RuntimeException('Tenant code is required for synchronization.');
        }

        $endpoint = $isDelta
            ? "{$serverUrl}/api/v1/desktop/sync/tenants/{$targetTenant}/delta"
            : "{$serverUrl}/api/v1/desktop/sync/tenants/{$targetTenant}/snapshot";

        $queryParams = [];
        if ($isDelta && $since !== null) {
            $queryParams['since'] = $since;
        }

        $request = Http::timeout($timeout)->acceptJson();
        if ($apiKey !== '') {
            $request->withToken($apiKey);
        }

        $response = $request->get($endpoint, $queryParams);

        if (! $response->successful()) {
            $errorMsg = $response->json('message') ?? $response->body();
            throw new RuntimeException("Cloud server sync request failed [HTTP {$response->status()}]: {$errorMsg}");
        }

        $snapshotPayload = $response->json();
        if (! is_array($snapshotPayload)) {
            throw new RuntimeException('Invalid response format received from sync server.');
        }

        $result = $this->ingestionService->ingest($snapshotPayload);
        $result['push'] = $pushResult;

        return $result;
    }

    /**
     * Check if cloud server is reachable.
     *
     * @return array<string, mixed>
     */
    public function pingServer(): array
    {
        $startTime = microtime(true);
        $serverUrl = rtrim((string) config('desktop.server.url', 'https://app.siupk.id'), '/');
        $apiKey = (string) config('desktop.server.api_key', '');
        $timeout = min(5, (int) config('desktop.server.timeout_seconds', 30));

        try {
            $request = Http::timeout($timeout)->acceptJson();
            if ($apiKey !== '') {
                $request->withToken($apiKey);
            }

            $response = $request->get("{$serverUrl}/api/v1/desktop/sync/status");
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'online' => $response->successful(),
                'status_code' => $response->status(),
                'latency_ms' => $latencyMs,
                'server_time' => $response->json('server_time'),
            ];
        } catch (\Throwable $e) {
            return [
                'online' => false,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Read reconnect status and the server-side subscription gate.
     *
     * @return array<string, mixed>
     */
    private function getSubscriptionGate(?string $tenantCode = null): array
    {
        $serverUrl = rtrim((string) config('desktop.server.url', 'https://app.siupk.id'), '/');
        $apiKey = (string) config('desktop.server.api_key', '');
        $timeout = min(5, (int) config('desktop.server.timeout_seconds', 30));

        try {
            $request = Http::timeout($timeout)->acceptJson();
            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $endpoint = isset($tenantCode)
                ? "{$serverUrl}/api/v1/desktop/sync/tenants/{$tenantCode}/status"
                : "{$serverUrl}/api/v1/desktop/sync/status";

            $response = $request->get($endpoint);

            if (! $response->successful()) {
                throw new RuntimeException("Subscription gate request failed [HTTP {$response->status()}].");
            }

            return $response->json();
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to verify subscription status before synchronization: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Get last synchronization details from local storage.
     *
     * @return array<string, mixed>|null
     */
    public function getLastSyncInfo(?string $connectionName = null): ?array
    {
        $conn = $connectionName ?? (string) config('tenancy.tenant_connection', 'sqlite');

        if (! Schema::connection($conn)->hasTable('tenant_registry')) {
            return null;
        }

        $tenant = DB::connection($conn)->table('tenant_registry')->first();
        if ($tenant === null) {
            return null;
        }

        return [
            'tenant_id' => $tenant->id,
            'tenant_code' => $tenant->code,
            'tenant_name' => $tenant->name,
            'synced_at' => $tenant->synced_at,
        ];
    }
}
