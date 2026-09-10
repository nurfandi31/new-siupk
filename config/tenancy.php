<?php

declare(strict_types=1);

return [
    'platform_connection' => 'platform',
    'tenant_connection' => 'tenant',

    'route_parameter' => env('TENANCY_ROUTE_PARAMETER', 'tenant'),
    'allow_header' => (bool) env('TENANCY_ALLOW_HEADER', false),
    'header' => env('TENANCY_HEADER', 'X-Tenant-Code'),
    'local_tenant' => env('TENANCY_LOCAL_TENANT', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Example Credential Store
    |--------------------------------------------------------------------------
    |
    | Production should replace ConfigShardCredentialProvider with an adapter
    | to a secret manager or encrypted credential store. The JSON object is
    | keyed by database_shards.credential_reference.
    |
    */
    'shard_credentials' => json_decode(
        (string) env('TENANCY_SHARD_CREDENTIALS_JSON', '{}'),
        true,
        flags: JSON_THROW_ON_ERROR,
    ),

    'shard_migration_path' => 'database/migrations/shard',
    'platform_migration_path' => 'database/migrations/platform',
];
