<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardTest extends TestCase
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
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_member_can_view_empty_dashboard(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('cards.0.key', 'cash')
                ->where('cards.0.value', 0)
                ->has('pipeline', 4)
                ->where('pipeline.0.count', 0)
                ->where('recent_journals', [])
                ->where('upcoming_due', [])
                ->where('counts.members', 0)
                ->where('counts.groups', 0)
                ->where('counts.active_loans', 0)
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }

    public function test_authenticated_user_without_membership_is_forbidden(): void
    {
        $this->createTenantMember();

        $stranger = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Stranger',
            'email' => 'stranger@example.test',
            'username' => 'stranger_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->actingAs($stranger)->get('/dashboard')->assertForbidden();
    }

    private function createTenantMember(): User
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
            'name' => 'Dashboard User',
            'email' => 'dashboard@example.test',
            'username' => 'dashboard_user',
            'password' => 'password',
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
