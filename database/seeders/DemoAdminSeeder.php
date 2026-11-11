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

/**
 * Tenant demo hanya dibuat bila SEED_DEMO_TENANT=true.
 *
 * Seeder ini tidak pernah menimpa password akun yang sudah ada sehingga data
 * operasional tidak berubah saat seeding diulang.
 */
final class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(env('SEED_DEMO_TENANT', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $tenantCode = (string) env('SEED_DEMO_TENANT_CODE', 'demo');
        $domain = (string) env('SEED_DEMO_DOMAIN', 'demo.siupknext.test');

        $tenant = Tenant::query()->firstOrCreate(
            ['code' => $tenantCode],
            [
                'public_id' => (string) Str::ulid(),
                'name' => (string) env('SEED_DEMO_TENANT_NAME', 'Demo Tenant'),
                'district_code' => (string) env('SEED_DEMO_DISTRICT_CODE', '320102'),
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'metadata' => ['domains' => [$domain]],
                'provisioned_at' => now(),
            ],
        );

        $shard = DatabaseShard::query()->firstOrCreate(
            ['code' => 'local'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Local Shard',
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

        $username = (string) env('SEED_DEMO_USERNAME', 'demo.admin');
        $existing = User::query()->where('username', $username)->first();

        if ($existing !== null) {
            $this->command?->info("Akun demo '{$username}' sudah ada; password tidak diubah.");

            return;
        }

        $password = (string) (env('SEED_DEMO_PASSWORD') ?: Str::password(20));

        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => (string) env('SEED_DEMO_NAME', 'Administrator Demo'),
            'username' => $username,
            'email' => (string) env('SEED_DEMO_EMAIL', 'admin@'.$domain),
            'phone' => env('SEED_DEMO_PHONE'),
            'password' => Hash::make($password),
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $user->row_id],
            [
                'tenant_id' => $tenant->row_id,
                'status' => 'active',
                'joined_at' => now(),
            ],
        );

        $this->command?->info(sprintf(
            'Akun demo %s dibuat untuk tenant %s. Password awal: %s',
            $username,
            $tenantCode,
            $password,
        ));
    }
}
