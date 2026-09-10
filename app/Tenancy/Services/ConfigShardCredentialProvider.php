<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Models\Platform\DatabaseShard;
use App\Tenancy\Contracts\ShardCredentialProvider;
use RuntimeException;

final class ConfigShardCredentialProvider implements ShardCredentialProvider
{
    public function credentialsFor(DatabaseShard $shard): array
    {
        if ($shard->driver === 'sqlite') {
            return [
                'username' => '',
                'password' => '',
            ];
        }

        $reference = (string) $shard->credential_reference;
        $credentials = config("tenancy.shard_credentials.{$reference}");

        if (! is_array($credentials)) {
            throw new RuntimeException("Shard credentials not found for reference [{$reference}].");
        }

        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if (! is_string($username) || ! is_string($password)) {
            throw new RuntimeException("Invalid shard credentials for reference [{$reference}].");
        }

        return [
            'username' => $username,
            'password' => $password,
        ];
    }
}
