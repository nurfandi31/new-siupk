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
 * Membuat akun awal platform tanpa kredensial dummy yang ditanam di kode.
 *
 * Nilai default hanya dipakai saat akun belum ada; password dapat ditentukan
 * lewat environment. Bila tidak diisi, password acak dibuat dan dicetak sekali
 * ke konsol agar operator dapat mencatatnya.
 */
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuperadmin();
        $this->seedDevelopmentTenant();
    }

    private function seedSuperadmin(): void
    {
        $username = (string) env('SEED_SUPERADMIN_USERNAME', 'superadmin');
        $existing = User::query()->where('username', $username)->first();

        if ($existing !== null) {
            $this->command?->info("Akun superadmin '{$username}' sudah ada; password tidak diubah.");

            return;
        }

        $password = (string) (env('SEED_SUPERADMIN_PASSWORD') ?: Str::password(20));

        User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => (string) env('SEED_SUPERADMIN_NAME', 'Superadmin Platform'),
            'username' => $username,
            'email' => (string) env('SEED_SUPERADMIN_EMAIL', 'superadmin@localhost'),
            'phone' => env('SEED_SUPERADMIN_PHONE'),
            'password' => Hash::make($password),
            'status' => 'active',
            'tenant_id' => null,
            'is_superadmin' => true,
        ]);

        $this->command?->info("Superadmin '{$username}' dibuat. Password awal: {$password}");
    }

    private function seedDevelopmentTenant(): void
    {
        if (! filter_var(env('SEED_DEV_TENANT', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

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

        $username = (string) env('SEED_DEV_USERNAME', 'dev');
        $existing = User::query()->where('username', $username)->first();

        if ($existing !== null) {
            $this->command?->info("Akun development '{$username}' sudah ada; password tidak diubah.");

            return;
        }

        $password = (string) (env('SEED_DEV_PASSWORD') ?: Str::password(20));

        $devUser = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => (string) env('SEED_DEV_NAME', 'Development User'),
            'username' => $username,
            'email' => (string) env('SEED_DEV_EMAIL', 'dev@localhost'),
            'phone' => env('SEED_DEV_PHONE'),
            'password' => Hash::make($password),
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $devUser->row_id],
            [
                'tenant_id' => $tenant->row_id,
                'status' => 'active',
                'joined_at' => now(),
            ],
        );

        $this->command?->info("Akun development '{$username}' dibuat. Password awal: {$password}");
    }
}
