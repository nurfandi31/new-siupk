<?php

declare(strict_types=1);

return [
    'merchant_code' => env('DUITKU_MERCHANT_CODE', ''),
    'api_key' => env('DUITKU_API_KEY', ''),
    'mode' => env('DUITKU_MODE', 'sandbox'),
    'default_method' => env('DUITKU_DEFAULT_METHOD', 'VC'),
    'callback_url' => env('DUITKU_CALLBACK_URL', ''),
    'return_url' => env('DUITKU_RETURN_URL', ''),
    'expiry_period' => (int) env('DUITKU_EXPIRY_PERIOD', 1440),
];
