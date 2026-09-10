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

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Provision Superadmin
        User::query()->updateOrCreate(
            ['username' => 'superadmin'],
            [
                'public_id' => User::query()->where('username', 'superadmin')->value('public_id') ?: (string) Str::ulid(),
                'name' => 'Superadmin Platform',
                'email' => 'superadmin@example.test',
                'password' => Hash::make('password'),
                'status' => 'active',
                'tenant_id' => null,
                'is_superadmin' => true,
            ],
        );

        // 2. Ensure Local Tenant & Shard exist for dev user
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

        $tenant = Tenant::query()->firstOrCreate(
            ['code' => 'local'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Local Development Tenant',
                'district_code' => '320101',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'metadata' => ['domains' => ['localhost', '127.0.0.1']],
                'provisioned_at' => now(),
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

        // 3. Provision Dev User
        $devUser = User::query()->updateOrCreate(
            ['username' => 'dev'],
            [
                'public_id' => User::query()->where('username', 'dev')->value('public_id') ?: (string) Str::ulid(),
                'tenant_id' => $tenant->row_id,
                'name' => 'Development User',
                'email' => 'dev@example.test',
                'password' => Hash::make('password'),
                'status' => 'active',
                'is_superadmin' => false,
            ],
        );

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $devUser->row_id],
            [
                'tenant_id' => $tenant->row_id,
                'status' => 'active',
                'joined_at' => now(),
            ],
        );
    }
}
