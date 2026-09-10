<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class DesktopOutboxService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(?string $connectionName = null): array
    {
        $connection = $connectionName ?? (string) config('database.default', 'sqlite');
        if (! Schema::connection($connection)->hasTable('outbox')) {
            return ['pending' => 0, 'failed' => 0, 'synced' => 0];
        }

        $rows = DB::connection($connection)->table('outbox')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($rows['pending'] ?? 0),
            'failed' => (int) ($rows['failed'] ?? 0),
            'synced' => (int) ($rows['synced'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function flushPendingMutations(?string $tenantCode = null, ?string $connectionName = null): array
    {
        $connection = $connectionName ?? (string) config('database.default', 'sqlite');
        $serverUrl = rtrim((string) config('desktop.server.url', 'https://app.siupk.id'), '/');
        $apiKey = (string) config('desktop.server.api_key', '');
        $tenant = $tenantCode ?? (string) config('desktop.server.tenant_code', 'default');
        $timeout = (int) config('desktop.server.timeout_seconds', 30);

        if (! Schema::connection($connection)->hasTable('outbox')) {
            return ['processed' => 0, 'accepted' => [], 'conflicts' => [], 'rejected' => []];
        }

        $accepted = [];
        $conflicts = [];
        $rejected = [];
        $processed = 0;

        while (true) {
            $mutations = DB::connection($connection)->table('outbox')
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(200)
                ->get();

            if ($mutations->isEmpty()) {
                break;
            }

            $decodedPayloads = [];
            foreach ($mutations as $mutation) {
                $decodedPayloads[$mutation->id] = json_decode((string) $mutation->payload, true) ?? [];
            }

            $payload = [
                'mutations' => array_map(fn ($mutation): array => [
                    'mutation_uuid' => $mutation->mutation_uuid,
                    'table_name' => $mutation->table_name,
                    'operation' => $mutation->operation,
                    'row_public_id' => $mutation->row_public_id,
                    'payload' => $decodedPayloads[$mutation->id],
                    'client_updated_at' => $decodedPayloads[$mutation->id]['updated_at'] ?? $mutation->created_at,
                ], $mutations->all()),
                'last_pulled_at' => $this->lastPulledAt($connection),
            ];

            DB::connection($connection)->table('outbox')->whereIn('id', $mutations->pluck('id'))->update([
                'attempts' => DB::raw('attempts + 1'),
            ]);

            try {
                $http = Http::timeout($timeout)->acceptJson();
                if ($apiKey !== '') {
                    $http->withToken($apiKey);
                }

                $response = $http->post("{$serverUrl}/api/v1/desktop/sync/tenants/{$tenant}/push", $payload);
                if (! $response->successful()) {
                    throw new RuntimeException(
                        "Desktop push failed [HTTP {$response->status()}]: ".(string) ($response->json('message') ?? $response->body()),
                    );
                }

                $uuidByOutcome = $this->groupUuids($response->json());
                $this->markOutcomes($connection, $mutations, $uuidByOutcome);

                $accepted = array_merge($accepted, $uuidByOutcome['accepted']);
                $conflicts = array_merge($conflicts, $response->json('conflicts', []));
                $rejected = array_merge($rejected, $response->json('rejected', []));
                $processed += $mutations->count();
            } catch (RuntimeException $exception) {
                foreach ($mutations as $mutation) {
                    DB::connection($connection)->table('outbox')->where('id', $mutation->id)->update([
                        'status' => 'failed',
                        'last_error' => $exception->getMessage(),
                    ]);
                }

                break;
            }
        }

        return [
            'processed' => $processed,
            'accepted' => $accepted,
            'conflicts' => $conflicts,
            'rejected' => $rejected,
        ];
    }

    private function groupUuids(array $response): array
    {
        return [
            'accepted' => array_values(array_filter((array) ($response['accepted'] ?? []))),
            'conflicts' => array_values(array_filter(array_map(
                fn ($item) => (string) ($item['mutation_uuid'] ?? ''),
                (array) ($response['conflicts'] ?? []),
            ))),
            'rejected' => array_values(array_filter(array_map(
                fn ($item) => (string) ($item['mutation_uuid'] ?? ''),
                (array) ($response['rejected'] ?? []),
            ))),
        ];
    }

    private function markOutcomes(string $connection, iterable $mutations, array $uuidByOutcome): void
    {
        foreach ($mutations as $mutation) {
            $status = 'failed';
            $error = 'Push rejected without a reason.';
            $pushedAt = null;

            if (in_array($mutation->mutation_uuid, $uuidByOutcome['accepted'], true)) {
                $status = 'synced';
                $error = null;
                $pushedAt = now()->toDateTimeString();
            } elseif (in_array($mutation->mutation_uuid, $uuidByOutcome['conflicts'], true)) {
                $status = 'synced';
                $error = 'Stored as server conflict for review.';
                $pushedAt = now()->toDateTimeString();
            } elseif (in_array($mutation->mutation_uuid, $uuidByOutcome['rejected'], true)) {
                $error = 'Rejected by cloud server.';
            }

            DB::connection($connection)->table('outbox')->where('id', $mutation->id)->update([
                'status' => $status,
                'pushed_at' => $pushedAt,
                'last_error' => $error,
            ]);
        }
    }

    private function lastPulledAt(string $connection): ?string
    {
        if (! Schema::connection($connection)->hasTable('tenant_registry')) {
            return null;
        }

        $syncedAt = DB::connection($connection)->table('tenant_registry')->min('synced_at');

        return $syncedAt !== null ? (string) $syncedAt : null;
    }
}
