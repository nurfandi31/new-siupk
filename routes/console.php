<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Backup is intentionally not scheduled here; the existing server cron remains authoritative.
// Add application-level housekeeping schedules below when needed.

Schedule::command('model:prune')->dailyAt('02:30');

// Otomatisasi Billing & Langganan
Schedule::command('subscriptions:generate-invoices')->dailyAt('01:00');
Schedule::command('subscriptions:check-overdue')->dailyAt('01:30');
