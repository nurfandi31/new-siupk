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

final class PipelineModalTest extends TestCase
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

    public function test_dashboard_without_pipeline_query_returns_null_modal(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('pipeline_modal', null)
                ->where('pipeline_modal_key', null)
            );
    }

    public function test_dashboard_with_invalid_pipeline_key_keeps_modal_null(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->get('/dashboard?pipeline=invalid');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('pipeline_modal', null)
                ->where('pipeline_modal_key', null)
            );
    }

    public function test_dashboard_with_valid_pipeline_key_returns_modal_payload(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->get('/dashboard?pipeline=proposal');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('pipeline_modal_key', 'proposal')
                ->has('pipeline_modal')
                ->where('pipeline_modal.key', 'proposal')
                ->where('pipeline_modal.label', 'Proposal')
                ->where('pipeline_modal.limit', 25)
                ->has('pipeline_modal.total')
                ->has('pipeline_modal.rows')
            );
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
            'email' => 'dashboard-pipeline@example.test',
            'username' => 'dashboard_pipeline_user',
            'password' => 'password',
            'status' => 'active',
        ]);

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

        config(['tenancy.local_tenant' => 'local']);

        return $user;
    }
}
