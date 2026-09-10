<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class DesktopSnapshotIngestionService
{
    /**
     * Ingest a full or delta snapshot into the local SQLite database.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function ingest(array $snapshot, ?string $connectionName = null): array
    {
        $startTime = microtime(true);
        $conn = $connectionName ?? (string) config('tenancy.tenant_connection', 'sqlite');

        $this->validateSnapshotStructure($snapshot);

        $type = (string) ($snapshot['type'] ?? 'full');
        $data = (array) ($snapshot['data'] ?? []);
        $meta = (array) ($snapshot['meta'] ?? []);
        $tablesOrder = (array) ($meta['tables_order'] ?? TenantSnapshotService::TABLES_IN_ORDER);

        $schema = Schema::connection($conn);
        $totalIngested = 0;
        $tableResults = [];

        // Temporarily disable foreign keys for seamless bulk operations
        if (DB::connection($conn)->getDriverName() === 'sqlite') {
            DB::connection($conn)->statement('PRAGMA foreign_keys = OFF;');
        }

        try {
            DB::connection($conn)->transaction(function () use ($conn, $schema, $tablesOrder, $data, $type, &$totalIngested, &$tableResults): void {
                $pendingRows = $this->pendingRowIdentifiers($conn, $schema);

                // If full snapshot, wipe existing data in reverse topological order first
                if ($type === 'full') {
                    $reverseOrder = array_reverse($tablesOrder);
                    foreach ($reverseOrder as $tableName) {
                        if (! $schema->hasTable($tableName)) {
                            continue;
                        }

                        $pending = $pendingRows[$tableName] ?? [];
                        if ($pending === []) {
                            DB::connection($conn)->table($tableName)->delete();
                        } else {
                            DB::connection($conn)->table($tableName)
                                ->whereNotIn('id', $pending)
                                ->delete();
                        }
                    }
                }

                // Insert/Upsert table by table in proper topological order
                foreach ($tablesOrder as $tableName) {
                    if (! isset($data[$tableName]) || ! is_array($data[$tableName])) {
                        continue;
                    }

                    if (! $schema->hasTable($tableName)) {
                        continue;
                    }

                    $rows = $data[$tableName];
                    if (empty($rows)) {
                        $tableResults[$tableName] = 0;

                        continue;
                    }

                    $count = 0;
                    $rows = array_values(array_filter($rows, function (array $row) use ($tableName, $pendingRows): bool {
                        $pending = $pendingRows[$tableName] ?? [];
                        $identifier = (string) ($row['public_id'] ?? $row['id'] ?? '');

                        return $identifier === '' || ! in_array($identifier, $pending, true);
                    }));
                    $chunks = array_chunk($rows, 100);

                    foreach ($chunks as $chunk) {
                        if ($type === 'full') {
                            DB::connection($conn)->table($tableName)->insert($chunk);
                            $count += count($chunk);
                        } else {
                            // For delta: upsert by primary key if available, otherwise delete and reinsert matching rows
                            $this->upsertDeltaChunk($conn, $tableName, $chunk);
                            $count += count($chunk);
                        }
                    }

                    $tableResults[$tableName] = $count;
                    $totalIngested += $count;
                }

                // Record sync timestamp in tenant_registry if present
                if ($schema->hasTable('tenant_registry') && isset($snapshot['tenant']['id'])) {
                    $tenantId = (int) $snapshot['tenant']['id'];
                    DB::connection($conn)->table('tenant_registry')
                        ->where('id', $tenantId)
                        ->update(['synced_at' => Carbon::parse($snapshot['generated_at'] ?? now())->toDateTimeString()]);
                }
            });
        } finally {
            if (DB::connection($conn)->getDriverName() === 'sqlite') {
                DB::connection($conn)->statement('PRAGMA foreign_keys = ON;');
            }
        }

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => 'success',
            'type' => $type,
            'tenant' => $snapshot['tenant'] ?? null,
            'total_tables' => count($tableResults),
            'total_records' => $totalIngested,
            'table_results' => $tableResults,
            'elapsed_ms' => $elapsedMs,
            'synced_at' => $snapshot['generated_at'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function validateSnapshotStructure(array $snapshot): void
    {
        if (($snapshot['format'] ?? '') !== 'siupk-desktop-snapshot-v1') {
            throw new InvalidArgumentException('Invalid snapshot format. Expected [siupk-desktop-snapshot-v1].');
        }

        if (! isset($snapshot['data']) || ! is_array($snapshot['data'])) {
            throw new InvalidArgumentException('Snapshot payload missing data block.');
        }

        $expectedChecksum = $snapshot['meta']['checksum'] ?? null;
        if ($expectedChecksum !== null) {
            $actualChecksum = hash('sha256', (string) json_encode($snapshot['data']));
            if (! hash_equals($expectedChecksum, $actualChecksum)) {
                throw new RuntimeException('Snapshot payload checksum mismatch. Data may be corrupted.');
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function pendingRowIdentifiers(string $conn, $schema): array
    {
        if (! $schema->hasTable('outbox')) {
            return [];
        }

        return DB::connection($conn)->table('outbox')
            ->whereIn('status', ['pending', 'failed'])
            ->where('operation', '!=', 'delete')
            ->get(['table_name', 'row_public_id'])
            ->groupBy('table_name')
            ->map(fn ($rows) => $rows->pluck('row_public_id')->all())
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     */
    private function upsertDeltaChunk(string $conn, string $tableName, array $chunk): void
    {
        $firstRow = $chunk[0] ?? [];
        if (isset($firstRow['row_id'])) {
            $rowIds = array_column($chunk, 'row_id');
            DB::connection($conn)->table($tableName)->whereIn('row_id', $rowIds)->delete();
        } elseif (isset($firstRow['id'])) {
            $ids = array_column($chunk, 'id');
            DB::connection($conn)->table($tableName)->whereIn('id', $ids)->delete();
        }

        DB::connection($conn)->table($tableName)->insert($chunk);
    }
}
