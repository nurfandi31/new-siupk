<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Desktop Mode Configuration
    |--------------------------------------------------------------------------
    |
    | When enabled, SIUPK Next behaves as a desktop application client.
    | Storage switches to local SQLite, external caches default to file,
    | and offline read-only guards are activated.
    |
    */
    'enabled' => (bool) env('DESKTOP_MODE', env('APP_ENV') === 'desktop'),

    /*
    |--------------------------------------------------------------------------
    | Local SQLite Database Path
    |--------------------------------------------------------------------------
    */
    'sqlite_database' => env('DESKTOP_SQLITE_PATH') ?: database_path('database.sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Cloud Synchronization Server Configuration
    |--------------------------------------------------------------------------
    */
    'server' => [
        'url' => rtrim((string) env('DESKTOP_SYNC_SERVER_URL', 'https://app.siupk.id'), '/'),
        'api_key' => (string) env('DESKTOP_SYNC_API_KEY', ''),
        'tenant_code' => (string) env('DESKTOP_TENANT_CODE', 'default'),
        'timeout_seconds' => (int) env('DESKTOP_SYNC_TIMEOUT', 30),
        'auto_sync_on_launch' => (bool) env('DESKTOP_AUTO_SYNC_ON_LAUNCH', true),
        'sync_interval_minutes' => (int) env('DESKTOP_SYNC_INTERVAL_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Electron Window Properties
    |--------------------------------------------------------------------------
    */
    'window' => [
        'title' => env('DESKTOP_APP_TITLE', 'SIUPK Next - Desktop Client'),
        'width' => (int) env('DESKTOP_WINDOW_WIDTH', 1440),
        'height' => (int) env('DESKTOP_WINDOW_HEIGHT', 900),
        'min_width' => 1024,
        'min_height' => 700,
        'resizable' => true,
        'fullscreenable' => true,
        'show_dev_tools' => (bool) env('DESKTOP_DEV_TOOLS', false),
    ],
];
