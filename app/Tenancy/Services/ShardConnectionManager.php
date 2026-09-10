<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Models\Platform\DatabaseShard;
use App\Tenancy\Contracts\ShardCredentialProvider;
use Illuminate\Support\Facades\DB;

final readonly class ShardConnectionManager
{
    public function __construct(
        private ShardCredentialProvider $credentialProvider,
    ) {}

    public function connect(DatabaseShard $shard): void
    {
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $credentials = $this->credentialProvider->credentialsFor($shard);

        if (($shard->driver ?: 'mysql') === 'sqlite') {
            config()->set("database.connections.{$connectionName}", [
                'driver' => 'sqlite',
                'database' => (string) $shard->database_name,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        } else {
            config()->set("database.connections.{$connectionName}", [
                'driver' => (string) ($shard->driver ?: 'mysql'),
                'host' => (string) $shard->host,
                'port' => (string) $shard->port,
                'database' => (string) $shard->database_name,
                'username' => $credentials['username'] ?? '',
                'password' => $credentials['password'] ?? '',
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ] : [],
            ]);
        }

        DB::purge($connectionName);
        DB::reconnect($connectionName);
        DB::connection($connectionName)->getPdo();
    }

    public function disconnect(): void
    {
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        DB::disconnect($connectionName);
        DB::purge($connectionName);
    }
}
