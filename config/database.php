<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'platform'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'platform' => [
            'driver' => env('PLATFORM_DB_DRIVER', 'mysql'),
            'url' => env('PLATFORM_DB_URL'),
            'host' => env('PLATFORM_DB_HOST', '127.0.0.1'),
            'port' => env('PLATFORM_DB_PORT', '3306'),
            'database' => env('PLATFORM_DB_DATABASE', 'siupk_platform'),
            'username' => env('PLATFORM_DB_USERNAME', 'root'),
            'password' => env('PLATFORM_DB_PASSWORD', ''),
            'unix_socket' => env('PLATFORM_DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'busy_timeout' => env('PLATFORM_DB_DRIVER') === 'sqlite' ? 60000 : null,
            'journal_mode' => env('PLATFORM_DB_DRIVER') === 'sqlite' ? 'wal' : null,
            'synchronous' => env('PLATFORM_DB_DRIVER') === 'sqlite' ? 'normal' : null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_EMULATE_PREPARES => false,
            ]) : [],
        ],

        // Placeholder. ShardConnectionManager replaces this at runtime.
        'tenant' => [
            'driver' => env('TENANT_DB_DRIVER', 'mysql'),
            'url' => env('TENANT_DB_URL'),
            'host' => env('TENANT_DB_HOST', '127.0.0.1'),
            'port' => env('TENANT_DB_PORT', '3306'),
            'database' => env('TENANT_DB_DATABASE', 'siupk_shard_placeholder'),
            'username' => env('TENANT_DB_USERNAME', 'root'),
            'password' => env('TENANT_DB_PASSWORD', ''),
            'unix_socket' => env('TENANT_DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'busy_timeout' => env('TENANT_DB_DRIVER') === 'sqlite' ? 60000 : null,
            'journal_mode' => env('TENANT_DB_DRIVER') === 'sqlite' ? 'wal' : null,
            'synchronous' => env('TENANT_DB_DRIVER') === 'sqlite' ? 'normal' : null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_EMULATE_PREPARES => false,
            ]) : [],
        ],

        // Legacy siupk source (SELECT only — never write from app code).
        'legacy' => [
            'driver' => 'mysql',
            'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB_PORT', '3306'),
            'database' => env('LEGACY_DB_DATABASE', 'siupk'),
            'username' => env('LEGACY_DB_USERNAME', 'root'),
            'password' => env('LEGACY_DB_PASSWORD', ''),
            'unix_socket' => env('LEGACY_DB_SOCKET', ''),
            'charset' => env('LEGACY_DB_CHARSET', 'utf8mb4'),
            'collation' => env('LEGACY_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_EMULATE_PREPARES => false,
            ]) : [],
        ],

        // enpii/assistant package — vector store for RAG embeddings.
        // ai_* tables live here so pgvector HNSW index gives sub-ms cosine search.
        // Falls back to a same-as-platform copy when unset (sqlite/mysql embed
        // vector as JSON; semantic search still works, just slower).
        'rag' => [
            'driver' => env('RAG_DB_CONNECTION', 'pgsql'),
            'host' => env('RAG_DB_HOST', '127.0.0.1'),
            'port' => env('RAG_DB_PORT', '5432'),
            'database' => env('RAG_DB_DATABASE', 'assistant'),
            'username' => env('RAG_DB_USERNAME', 'assistant'),
            'password' => env('RAG_DB_PASSWORD', 'assistant'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('RAG_DB_SSLMODE', 'prefer'),
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
