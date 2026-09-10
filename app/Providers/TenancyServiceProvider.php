<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\InitializeTenantSequences;
use App\Console\Commands\MigrateTenantShards;
use App\Console\Commands\ProvisionDevUser;
use App\Console\Commands\ProvisionSuperadmin;
use App\Console\Commands\SyncTenantRegistry;
use App\Tenancy\Contracts\ShardCredentialProvider;
use App\Tenancy\Services\ConfigShardCredentialProvider;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use App\Tenancy\TenantScope;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(TenantResolver::class);
        $this->app->singleton(ShardCredentialProvider::class, ConfigShardCredentialProvider::class);
        $this->app->singleton(ShardConnectionManager::class);
        $this->app->singleton(TenantSequenceService::class);

        $this->app->bind(TenantScope::class, fn (Application $app): TenantScope => new TenantScope(
            $app->make(TenantContext::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateTenantShards::class,
                ProvisionDevUser::class,
                ProvisionSuperadmin::class,
                SyncTenantRegistry::class,
                InitializeTenantSequences::class,
            ]);
        }
    }
}
