<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['code' => 'demo'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Demo Tenant SIUPK Next',
                'district_code' => '320101',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'metadata' => ['domains' => ['demo.siupknext.test']],
                'provisioned_at' => now(),
            ],
        );

        $shard = DatabaseShard::query()->firstOrCreate(
            ['code' => 'local'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Local Development Shard',
                'driver' => 'mysql',
                'host' => (string) config('database.connections.tenant.host', 'mysql'),
                'port' => (int) config('database.connections.tenant.port', 3306),
                'database_name' => (string) config('database.connections.tenant.database', 'siupk_shard_local'),
                'credential_reference' => 'local',
                'placement_type' => 'shared',
                'status' => 'active',
            ],
        );

        TenantPlacement::query()->firstOrCreate(
            ['tenant_id' => $tenant->row_id],
            [
                'shard_id' => $shard->row_id,
                'status' => 'active',
                'placed_at' => now(),
            ],
        );

        $user = User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'public_id' => User::query()->where('username', 'admin')->value('public_id') ?: (string) Str::ulid(),
                'tenant_id' => $tenant->row_id,
                'name' => 'Administrator Demo',
                'email' => 'admin@demo.siupknext.test',
                'phone' => '+6280000000001',
                'password' => Hash::make('password'),
                'status' => 'active',
                'is_superadmin' => false,
            ],
        );

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $user->row_id],
            [
                'tenant_id' => $tenant->row_id,
                'status' => 'active',
                'joined_at' => now(),
            ],
        );

        $this->command?->info(sprintf(
            'User admin di tenant demo siap: username=admin password=password (tenant_id=%s, shard=%s)',
            (string) $tenant->row_id,
            (string) $shard->code,
        ));
    }
}
