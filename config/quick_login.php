<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Quick Login (Tenant User Switcher)
|--------------------------------------------------------------------------
|
| The /login/user page lists every login-capable user that belongs to the
| tenant resolved from the current host. Double-clicking a card signs the
| visitor in without a password, which is handy for demos and local
| development but dangerous on a real production deployment.
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
