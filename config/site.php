<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Public Site Hosts
    |--------------------------------------------------------------------------
    |
    | Extra hostnames (comma separated) that must always render the platform
    | marketing page instead of a tenant site, e.g. the bare server host used
    | behind a load balancer when APP_URL already points at a friendly name.
    |
    | Example: SITE_PLATFORM_HOSTS=siupk.example.com,app.internal
    |
    */

    'platform_hosts' => env('SITE_PLATFORM_HOSTS', ''),
];
