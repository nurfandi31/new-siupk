<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SingleDeviceSessionTest extends TestCase
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

    public function test_logging_in_from_new_device_invalidates_previous_session(): void
    {
        $password = 'secret-password-123';
        $user = $this->createTenantUser($password);

        // 1. Device 1 logs in
        $responseDevice1 = $this->post('/login', [
            'identifier' => $user->username,
            'password' => $password,
        ]);
        $responseDevice1->assertRedirect('/dashboard');
        $session1Id = session()->getId();
        $session1Data = session()->all();

        // 2. Device 2 logs in
        Auth::forgetGuards();
        $this->flushSession();
        $this->defaultCookies = [];

        $responseDevice2 = $this->post('/login', [
            'identifier' => $user->username,
            'password' => $password,
        ]);
        $responseDevice2->assertRedirect('/dashboard');
        $session2Id = session()->getId();
        $session2Data = session()->all();

        // 3. Device 2 can access dashboard
        $this->withSession($session2Data)
            ->get('/dashboard')
            ->assertOk();

        // 4. Device 1 attempts to access dashboard with its old session data
        $this->flushSession();
        Auth::forgetGuards();
        $this->defaultCookies = [];

        $responseDevice1After = $this->withSession($session1Data)
            ->get('/dashboard');

        $responseDevice1After->assertRedirect('/login');
    }

    private function createTenantUser(string $password): User
    {
        $tenantDb = (string) config('database.connections.tenant.database');

        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => (int) config('database.connections.tenant.port', 3306),
            'database_name' => $tenantDb,
            'credential_reference' => str_ends_with($tenantDb, '_test') ? 'test' : 'local',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['localhost']],
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => 'Single Device User',
            'email' => 'singledevice@example.test',
            'username' => 'singledevice_user',
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);
        Artisan::call('tenancy:sync-registry', ['--shard' => 'local']);

        config(['tenancy.local_tenant' => 'local']);

        return $user;
    }
}
