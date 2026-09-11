<?php

declare(strict_types=1);

namespace Tests\Feature\Desktop;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DesktopFoundationTest extends TestCase
{
    public function test_desktop_status_command_executes_successfully(): void
    {
        $this->artisan('desktop:status')
            ->expectsOutputToContain('=== siupk Next Desktop Client Status ===')
            ->assertSuccessful();
    }

    public function test_desktop_init_command_creates_and_migrates_sqlite(): void
    {
        $tempSqlite = database_path('test_desktop_init.sqlite');
        Config::set('desktop.sqlite_database', $tempSqlite);

        try {
            $exitCode = Artisan::call('desktop:init', ['--force' => true]);
            $this->assertSame(0, $exitCode);
            $this->assertTrue(File::exists($tempSqlite));

            // Verify core tables exist in SQLite schema
            $this->assertTrue(Schema::connection('sqlite')->hasTable('tenant_registry'));
            $this->assertTrue(Schema::connection('sqlite')->hasTable('accounts'));
            $this->assertTrue(Schema::connection('sqlite')->hasTable('members'));
            $this->assertTrue(Schema::connection('sqlite')->hasTable('loans'));
            $this->assertTrue(Schema::connection('sqlite')->hasTable('journal_entries'));
        } finally {
            if (File::exists($tempSqlite)) {
                File::delete($tempSqlite);
            }
        }
    }

    public function test_electron_scaffold_files_exist(): void
    {
        $this->assertTrue(File::exists(base_path('electron/main.cjs')));
        $this->assertTrue(File::exists(base_path('electron/preload.cjs')));
        $this->assertTrue(File::exists(base_path('electron/electron-builder.json')));
        $this->assertTrue(File::exists(base_path('.env.desktop.example')));
    }

    public function test_desktop_configuration_defaults(): void
    {
        $this->assertIsArray(config('desktop.server'));
        $this->assertIsArray(config('desktop.window'));
        $this->assertSame(1440, config('desktop.window.width'));
        $this->assertSame(900, config('desktop.window.height'));
    }

    public function test_root_url_redirects_to_login_for_desktop_clients(): void
    {
        // 1. Regular web client visits / -> receives OK (renders Home)
        Config::set('desktop.enabled', false);
        $webResponse = $this->get('/');
        $webResponse->assertOk();

        // 2. Desktop mode enabled -> redirects to /login
        Config::set('desktop.enabled', true);
        $desktopResponse = $this->get('/');
        $desktopResponse->assertRedirect('/login');

        // 3. Desktop header sent -> redirects to /login
        Config::set('desktop.enabled', false);
        $headerResponse = $this->withHeader('X-Desktop-Client', '1')->get('/');
        $headerResponse->assertRedirect('/login');
    }
}
