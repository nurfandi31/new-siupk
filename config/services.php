<?php

declare(strict_types=1);

return [
    'regional_api' => [
        'base_url' => env('REGIONAL_CODE_API_URL', 'https://api.kodewilayah.web.id'),
        'timeout' => (int) env('REGIONAL_CODE_API_TIMEOUT', 10),
    ],
    'wa_gateway' => [
        'base_url' => rtrim((string) env('WA_GATEWAY_BASE', ''), '/'),
        'api_key' => (string) env('WA_GATEWAY_API_KEY', ''),
        'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 15),
        'instance_prefix' => (string) env('WA_GATEWAY_INSTANCE_PREFIX', 'app-siupk'),
    ],
    'holding' => [
        'api_key' => env('HOLDING_API_KEY', env('HOLDING_API_TOKEN', env('HOLDING_SECRET', ''))),
        'enabled' => (bool) env('HOLDING_API_ENABLED', true),
    ],
    'desktop' => [
        'api_key' => env('DESKTOP_SYNC_API_KEY', env('DESKTOP_API_KEY', '')),
        'enabled' => (bool) env('DESKTOP_SYNC_ENABLED', true),
    ],
];
