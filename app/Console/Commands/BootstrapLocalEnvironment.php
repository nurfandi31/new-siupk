<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\FiscalPeriodProvisioner;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use App\Tenancy\Services\TenantRegistrySynchronizer;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

final class BootstrapLocalEnvironment extends Command
{
    protected $signature = 'siupk:bootstrap-local
        {--password= : Dev user password; skip user provisioning when empty}
        {--username=dev : Dev username}
        {--email=dev@example.test : Dev email}
        {--tenant=local : Tenant code}
        {--district= : Optional 6-digit district code for village provisioning}
        {--years=1 : How many fiscal years to open from current year}';

    protected $description = 'Idempotent local bootstrap: shard, tenant, migrations, COA, fiscal periods, defaults.';

    public function handle(
        ShardConnectionManager $connections,
        TenantRegistrySynchronizer $registry,
        TenantContext $context,
        TenantGroupMasterDataProvisioner $groupMasterData,
        TenantLoanProductProvisioner $loanProducts,
    ): int {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('siupk:bootstrap-local is restricted to local/testing.');
        }

        $tenantCode = (string) $this->option('tenant');
        $shard = $this->ensureLocalShard();
        $tenant = $this->ensureLocalTenant($tenantCode, $shard);

        $this->info('Migrating shard [local]...');
        $exit = Artisan::call('tenancy:migrate-shards', ['--shard' => 'local', '--force' => true]);
        $this->output->write(Artisan::output());
        if ($exit !== self::SUCCESS) {
            return $exit;
        }

        $this->info('Syncing tenant registry...');
        $exit = Artisan::call('tenancy:sync-registry', ['--shard' => 'local', '--tenant' => $tenantCode]);
        $this->output->write(Artisan::output());
        if ($exit !== self::SUCCESS) {
            return $exit;
        }

        $placement = $tenant->fresh(['placement.shard'])->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Local tenant placement missing after bootstrap.');
        }

        $connections->connect($shard);
        try {
            $registry->sync($tenant);
            $context->initialize($tenant, $placement, $shard);

            $this->info('Importing chart of accounts...');
            $coa = app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
            $this->info("COA inserted={$coa['inserted']} skipped={$coa['skipped']}");

            $created = app(FiscalPeriodProvisioner::class)
                ->ensureDefaults((int) $this->option('years'));
            $this->info("Fiscal periods: opened {$created} month(s).");

            $groupMasterData->ensureDefaults();
            $loanProducts->ensureDefaults();
            app(PermissionChecker::class)->ensureSystemRoles();
            $this->info('Master data + loan products + roles ready.');
        } finally {
            $context->clear();
            $connections->disconnect();
        }

        $password = (string) ($this->option('password') ?? '');
        if ($password !== '') {
            $exit = Artisan::call('siupk:provision-dev-user', [
                '--username' => (string) $this->option('username'),
                '--email' => (string) $this->option('email'),
                '--password' => $password,
                '--tenant' => $tenantCode,
            ]);
            $this->output->write(Artisan::output());
            if ($exit !== self::SUCCESS) {
                return $exit;
            }
        } else {
            $this->warn('No --password given; skipped siupk:provision-dev-user.');
        }

        $this->newLine();
        $this->info('Local environment ready.');
        $this->line('  App:    '.rtrim((string) config('app.url'), '/').'/login');
        $this->line("  Tenant: {$tenantCode}");
        if ($password !== '') {
            $this->line('  User:   '.(string) $this->option('username').' / (password you set)');
        }

        return self::SUCCESS;
    }

    private function ensureLocalShard(): DatabaseShard
    {
        $host = (string) config('database.connections.tenant.host', 'mysql');
        $database = (string) config('database.connections.tenant.database', 'siupk_shard_local');
        $port = (int) config('database.connections.tenant.port', 3306);

        $shard = DatabaseShard::query()->updateOrCreate(
            ['code' => 'local'],
            [
                'public_id' => DatabaseShard::query()->where('code', 'local')->value('public_id')
                    ?? (string) Str::ulid(),
                'name' => 'Local Development Shard',
                'driver' => 'mysql',
                'host' => $host,
                'port' => $port,
                'database_name' => $database,
                'credential_reference' => 'local',
                'placement_type' => 'shared',
                'status' => 'active',
            ],
        );

        $this->line("Shard [{$shard->code}] â†’ {$shard->host}/{$shard->database_name}");

        return $shard;
    }

    private function ensureLocalTenant(string $code, DatabaseShard $shard): Tenant
    {
        $district = $this->option('district');
        $districtCode = is_string($district) && preg_match('/^\d{6}$/', $district) === 1
            ? $district
            : null;

        $tenant = Tenant::query()->updateOrCreate(
            ['code' => $code],
            [
                'public_id' => Tenant::query()->where('code', $code)->value('public_id')
                    ?? (string) Str::ulid(),
                'name' => 'Local Development Tenant',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'district_code' => $districtCode ?? Tenant::query()->where('code', $code)->value('district_code'),
                'metadata' => [
                    'domains' => ['localhost', '127.0.0.1', 'siupknext-nginx-1'],
                ],
                'provisioned_at' => now(),
            ],
        );

        TenantPlacement::query()->updateOrCreate(
            ['tenant_id' => $tenant->row_id],
            [
                'shard_id' => $shard->row_id,
                'status' => 'active',
                'placed_at' => now(),
            ],
        );

        $this->line("Tenant [{$tenant->code}] row_id={$tenant->row_id}");

        return $tenant->fresh(['placement.shard']);
    }
}
