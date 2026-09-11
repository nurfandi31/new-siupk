<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

final class DesktopInitCommand extends Command
{
    protected $signature = 'desktop:init {--force : Force recreation of local SQLite database}';

    protected $description = 'Initialize local SQLite database and environment for siupk Next Desktop Client';

    public function handle(): int
    {
        $this->info('Initializing siupk Next Desktop Client...');

        $sqlitePath = (string) config('desktop.sqlite_database', database_path('database.sqlite'));
        $directory = dirname($sqlitePath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->line("Created directory: <comment>{$directory}</comment>");
        }

        if (File::exists($sqlitePath) && $this->option('force')) {
            File::delete($sqlitePath);
            $this->warn("Removed existing database at: <comment>{$sqlitePath}</comment>");
        }

        if (! File::exists($sqlitePath)) {
            File::put($sqlitePath, '');
            $this->info("Created empty SQLite database at: <comment>{$sqlitePath}</comment>");
        } else {
            $this->line("Using existing SQLite database at: <comment>{$sqlitePath}</comment>");
        }

        // Configure runtime connections to SQLite
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $sqlitePath);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('tenancy.tenant_connection', 'sqlite');

        $this->info('Running shard migrations on SQLite connection...');
        $exitCode = Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ], $this->output);

        if ($exitCode === 0) {
            $this->info('Desktop local SQLite initialization completed successfully!');

            return self::SUCCESS;
        }

        $this->error('Failed to run migrations on SQLite database.');

        return self::FAILURE;
    }
}
