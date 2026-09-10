<?php

declare(strict_types=1);

return [
    'secret_key' => env('XENDIT_SECRET_KEY', ''),
    'public_key' => env('XENDIT_PUBLIC_KEY', ''),
    'callback_token' => env('XENDIT_CALLBACK_TOKEN', ''),
    'mode' => env('XENDIT_MODE', 'sandbox'),
    'default_method' => env('XENDIT_DEFAULT_METHOD', 'QRIS'),
    'expiry_period' => (int) env('XENDIT_EXPIRY_PERIOD', 86400),
];
