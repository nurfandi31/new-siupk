<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/t/local/health')->assertRedirect('/login');
    }

    public function test_member_can_access_tenant_health(): void
    {
        [$user] = $this->createTenantWithUser();

        $this->actingAs($user)
            ->get('/t/local/health')
            ->assertOk()
            ->assertJsonPath('tenant_code', 'local')
            ->assertJsonPath('shard', 'local');
    }

    public function test_non_member_is_forbidden(): void
    {
        [, $tenant] = $this->createTenantWithUser();
        $user = $this->createUser();

        $this->actingAs($user)
            ->get('/t/'.$tenant->code.'/health')
            ->assertForbidden();
    }

    private function createTenantWithUser(): array
    {
        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => 'mysql',
            'port' => 3306,
            'database_name' => (string) config('database.connections.tenant.database'),
            'credential_reference' => 'local',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);
        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);
        $user = $this->createUser();
        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);
        Artisan::call('tenancy:sync-registry', ['--shard' => 'local']);

        return [$user, $tenant];
    }

    private function createUser(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Test User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'user_'.Str::lower(Str::random(12)),
            'password' => 'password',
            'status' => 'active',
        ]);
    }
}
