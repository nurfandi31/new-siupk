<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Quick Login (User Switcher)
|--------------------------------------------------------------------------
|
| The card switcher is split into two explicit URLs so the list filter never
| mixes platform and tenant users:
|
|   /user              -> platform/central users only (superadmin, province
|                         and regency supervisors).
|   /login/user        -> users of the tenant/location resolved automatically
|                         from the current host.
|
| An optional /t/{tenant}/user URL is available when several tenants share
| the same host (e.g. local development).
|
| Double-clicking a card signs the visitor in without a password, which is
| handy for demos and local development but dangerous on a real production
| deployment.
|
| The feature is therefore enabled by default only outside production.
| Set QUICK_LOGIN_ENABLED=true to force-enable it (e.g. on a public demo
| server) or QUICK_LOGIN_ENABLED=false to disable it everywhere.
|
*/

return [
    'enabled' => env(
        'QUICK_LOGIN_ENABLED',
        env('APP_ENV', 'production') !== 'production',
    ),
];
