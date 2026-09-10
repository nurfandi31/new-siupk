<?php

declare(strict_types=1);

namespace App\Domain\Sync\Observers;

use App\Domain\Sync\Contracts\ExcludedFromDesktopSync;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class DesktopOutboxObserver
{
    public function created(Model $model): void
    {
        $this->enqueue($model, 'insert');
    }

    public function updated(Model $model): void
    {
        $this->enqueue($model, 'update');
    }

    public function deleted(Model $model): void
    {
        $this->enqueue($model, 'delete', $this->lastKnownRow($model));
    }

    private function enqueue(Model $model, string $operation, ?array $payload = null): void
    {
        if (! $model instanceof TenantModel
            || $model instanceof ExcludedFromDesktopSync
            || DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $connection = config('database.default');
        if (! DB::connection($connection)->getSchemaBuilder()->hasTable('outbox')) {
            return;
        }

        $payload ??= $this->lastKnownRow($model);
        if ($payload === null) {
            return;
        }

        DB::connection($connection)->table('outbox')->insert([
            'mutation_uuid' => (string) Str::uuid(),
            'table_name' => $model->getTable(),
            'operation' => $operation,
            'row_public_id' => $this->rowPublicId($payload),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now()->toDateTimeString(),
            'pushed_at' => null,
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
        ]);
    }

    private function lastKnownRow(Model $model): ?array
    {
        $attributes = $model->getAttributes();
        if ($attributes !== []) {
            $attributes['tenant_id'] ??= 1;
            $attributes['id'] ??= 1;

            return $this->normalizePayload($attributes);
        }

        $key = $model->getKey();
        if ($key === null) {
            return null;
        }

        $row = DB::connection($model->getConnectionName())
            ->table($model->getTable())
            ->where($model->getKeyName(), $key)
            ->first();

        if ($row === null) {
            $original = $model->getOriginal();
            if ($original === []) {
                throw new RuntimeException('Deleted tenant row has no outbox payload.');
            }

            return $this->normalizePayload($original);
        }

        return $this->normalizePayload((array) $row);
    }

    private function normalizePayload(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \BackedEnum || $value instanceof \UnitEnum) {
                $row[$key] = $value instanceof \BackedEnum ? $value->value : $value->name;
            }
        }

        return $row;
    }

    private function rowPublicId(array $row): string
    {
        return (string) ($row['public_id'] ?? $row['id'] ?? '');
    }
}
