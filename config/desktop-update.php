<?php

declare(strict_types=1);

return [
    'server_version' => env('APP_VERSION', '1.0.0'),
    'latest_version' => env('DESKTOP_LATEST_VERSION', '1.0.0'),
    'min_version' => env('DESKTOP_MIN_VERSION', '1.0.0'),
    'download_url' => env('DESKTOP_DOWNLOAD_URL', 'https://github.com/its-enpii/new_siupk/releases/latest/download'),
    'release_notes_url' => env('DESKTOP_RELEASE_NOTES_URL', 'https://github.com/its-enpii/new_siupk/releases/latest'),
    'sha512' => env('DESKTOP_SHA512'),
];
