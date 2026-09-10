<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class TenantSequenceService
{
    public function __construct(
        private TenantContext $context,
    ) {}

    public function next(string $sequenceName): int
    {
        $sequenceName = trim($sequenceName);

        if ($sequenceName === '') {
            throw new InvalidArgumentException('Sequence name cannot be empty.');
        }

        $connection = DB::connection((string) config('tenancy.tenant_connection', 'tenant'));
        $tenantId = $this->context->id();

        return $connection->transaction(function (ConnectionInterface $db) use ($tenantId, $sequenceName): int {
            $db->table('tenant_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'sequence_name' => $sequenceName,
                'next_value' => 1,
                'updated_at' => now(),
            ]);

            $sequence = $db->table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('sequence_name', $sequenceName)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException("Unable to initialize sequence [{$sequenceName}].");
            }

            $nextValue = (int) $sequence->next_value;

            $db->table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('sequence_name', $sequenceName)
                ->update([
                    'next_value' => $nextValue + 1,
                    'updated_at' => now(),
                ]);

            return $nextValue;
        }, 5);
    }

    public function initializeAtLeast(string $sequenceName, int $nextValue): void
    {
        if ($nextValue < 1) {
            throw new InvalidArgumentException('Next sequence value must be at least 1.');
        }

        $connection = DB::connection((string) config('tenancy.tenant_connection', 'tenant'));
        $tenantId = $this->context->id();

        $connection->transaction(function (ConnectionInterface $db) use ($tenantId, $sequenceName, $nextValue): void {
            $db->table('tenant_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'sequence_name' => $sequenceName,
                'next_value' => $nextValue,
                'updated_at' => now(),
            ]);

            $sequence = $db->table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('sequence_name', $sequenceName)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException("Unable to initialize sequence [{$sequenceName}].");
            }

            if ((int) $sequence->next_value < $nextValue) {
                $db->table('tenant_sequences')
                    ->where('tenant_id', $tenantId)
                    ->where('sequence_name', $sequenceName)
                    ->update([
                        'next_value' => $nextValue,
                        'updated_at' => now(),
                    ]);
            }
        }, 5);
    }
}
