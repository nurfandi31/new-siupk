<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\FiscalPeriodProvisioner;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantGroupMasterDataProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use App\Tenancy\Services\TenantRegistrySynchronizer;
use App\Tenancy\Services\TenantVillageProvisioner;
use App\Tenancy\Services\TenantWorkbench;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class TenantRegistrationService
{
    public function __construct(
        private ShardConnectionManager $connections,
        private TenantRegistrySynchronizer $registry,
        private TenantVillageProvisioner $villages,
        private TenantGroupMasterDataProvisioner $groupMasterData,
        private TenantLoanProductProvisioner $loanProducts,
        private DefaultChartOfAccountsProvisioner $coa,
        private FiscalPeriodProvisioner $fiscalPeriods,
        private PermissionChecker $permissions,
        private TenantContext $context,
        private TenantWorkbench $workbench,
    ) {}

    public function register(array $data): Tenant
    {
        [$tenant, $placement, $shard, $user] = DB::connection('platform')->transaction(function () use ($data): array {
            $shard = DatabaseShard::query()->where('status', 'active')->orderBy('row_id')->firstOrFail();
            $tenant = Tenant::query()->create([
                'public_id' => (string) Str::ulid(),
                'code' => $this->tenantCode($data['name']),
                'name' => $data['name'],
                'district_code' => $data['district_code'],
                'map_latitude' => $data['map_latitude'] ?? null,
                'map_longitude' => $data['map_longitude'] ?? null,
                'map_zoom' => $data['map_zoom'] ?? null,
                'status' => 'provisioning',
                'timezone' => 'Asia/Jakarta',
            ]);
            $placement = TenantPlacement::query()->create([
                'tenant_id' => $tenant->row_id,
                'shard_id' => $shard->row_id,
                'status' => 'active',
                'placed_at' => now(),
            ]);
            $user = User::query()->create([
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $tenant->row_id,
                'name' => $data['user_name'],
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);
            TenantMembership::query()->create([
                'tenant_id' => $tenant->row_id,
                'user_id' => $user->row_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return [$tenant, $placement, $shard, $user];
        });

        try {
            $this->connections->connect($shard);
            $this->registry->sync($tenant);
            $this->context->initialize($tenant, $placement, $shard);

            $this->groupMasterData->ensureDefaults();
            $this->villages->provision($tenant);
            $this->loanProducts->ensureDefaults();
            $this->coa->ensureDefaults();
            $this->fiscalPeriods->ensureDefaults(1);
            $this->permissions->ensureSystemRoles();
            $this->permissions->assignRole($user, 'admin');

            $tenant->forceFill(['status' => 'active', 'provisioned_at' => now()])->save();
            $this->registry->sync($tenant);

            return $tenant->fresh();
        } catch (\Throwable $exception) {
            $tenant->forceFill(['status' => 'provisioning_failed'])->saveQuietly();
            throw new RuntimeException('Tenant provisioning failed.', previous: $exception);
        } finally {
            $this->context->clear();
            $this->connections->disconnect();
        }
    }

    /**
     * Re-run idempotent provision steps for an existing tenant (repair).
     *
     * @return array{coa: array, fiscal_created: int, roles: bool}
     */
    public function repair(Tenant $tenant): array
    {
        $tenant->loadMissing('placement.shard');
        $shard = $tenant->placement?->shard;
        if ($shard !== null) {
            Artisan::call('tenancy:migrate-shards', [
                '--shard' => $shard->code,
                '--force' => true,
            ]);
        }

        return $this->workbench->run($tenant, function () use ($tenant): array {
            $this->registry->sync($tenant);
            $this->groupMasterData->ensureDefaults();
            $this->villages->provision($tenant);
            $this->loanProducts->ensureDefaults();
            $coa = $this->coa->ensureDefaults();
            $fiscal = $this->fiscalPeriods->ensureDefaults(1);
            $this->permissions->ensureSystemRoles();

            try {
                if (file_exists(base_path('vendor/enpii/assistant/database/migrations'))) {
                    Artisan::call('migrate', [
                        '--database' => 'rag',
                        '--path' => 'vendor/enpii/assistant/database/migrations',
                        '--force' => true,
                    ]);
                }
            } catch (\Throwable) {
                // Ignore RAG postgres connection errors if RAG DB disabled
            }

            return [
                'coa' => $coa,
                'fiscal_created' => $fiscal,
                'roles' => true,
            ];
        });
    }

    private function tenantCode(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $code = $base;

        for ($suffix = 2; Tenant::query()->where('code', $code)->exists(); $suffix++) {
            $code = $base.'-'.$suffix;
        }

        return $code;
    }
}
