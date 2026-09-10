<?php

declare(strict_types=1);

return [
    'merchant_code' => env('TRIPAY_MERCHANT_CODE', ''),
    'api_key' => env('TRIPAY_API_KEY', ''),
    'private_key' => env('TRIPAY_PRIVATE_KEY', ''),
    'mode' => env('TRIPAY_MODE', 'sandbox'),
    'default_method' => env('TRIPAY_DEFAULT_METHOD', 'QRIS2'),
    'callback_url' => env('TRIPAY_CALLBACK_URL'),
];
