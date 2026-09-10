<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\DesktopInitCommand;
use App\Console\Commands\DesktopStatusCommand;
use App\Console\Commands\DesktopSyncCommand;
use App\Domain\Sync\Observers\DesktopOutboxObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

final class DesktopAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/desktop.php', 'desktop');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DesktopInitCommand::class,
                DesktopStatusCommand::class,
                DesktopSyncCommand::class,
            ]);
        }

        if (config('database.default') === 'sqlite') {
            Model::observe(DesktopOutboxObserver::class);
        }

        // When running in desktop mode, ensure the local SQLite file exists
        if ((bool) config('desktop.enabled', false)) {
            $this->ensureSqliteExists();
        }
    }

    private function ensureSqliteExists(): void
    {
        $sqlitePath = (string) config('desktop.sqlite_database', database_path('database.sqlite'));
        $directory = dirname($sqlitePath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (! File::exists($sqlitePath)) {
            File::put($sqlitePath, '');
        }
    }
}
