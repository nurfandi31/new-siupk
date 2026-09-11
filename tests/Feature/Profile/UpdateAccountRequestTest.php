<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UpdateAccountRequestTest extends TestCase
{
    private const CURRENT_PASSWORD = 'current-password';

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

    public function test_user_can_update_account_username_and_password(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->put(route('profile.account.update'), [
            'username' => 'updated_username',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('profile.edit', ['tab' => 'account']));

        $freshUser = User::query()->find($user->row_id);
        $this->assertSame('updated_username', $freshUser->username);
        $this->assertTrue(Hash::check('new-secure-password', $freshUser->password));
    }

    public function test_user_cannot_use_username_taken_by_another_user(): void
    {
        $user = $this->createTenantMember();
        $otherUser = $this->createTenantMember([
            'username' => 'duplicate_username',
        ]);

        $response = $this->actingAs($user)->put(route('profile.account.update'), [
            'username' => $otherUser->username,
            'password' => self::CURRENT_PASSWORD,
            'password_confirmation' => self::CURRENT_PASSWORD,
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertSame('initial_username', User::query()->find($user->row_id)->username);
    }

    public function test_user_can_keep_their_current_username(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->put(route('profile.account.update'), [
            'username' => $user->username,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('profile.edit', ['tab' => 'account']));
        $this->assertSame('initial_username', User::query()->find($user->row_id)->username);
        $this->assertTrue(Hash::check('new-secure-password', User::query()->find($user->row_id)->password));
    }

    private function createTenantMember(array $attributes = [], string $fixtureCode = 'local'): User
    {
        $tenantDb = (string) config('database.connections.tenant.database');

        $shard = DatabaseShard::query()->firstWhere('code', $fixtureCode);
        $fixtureWasCreated = $shard === null;

        if ($shard === null) {
            $shard = DatabaseShard::query()->create([
                'public_id' => (string) Str::ulid(),
                'code' => $fixtureCode,
                'name' => 'Local Shard',
                'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
                'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
                'port' => (int) config('database.connections.tenant.port', 3306),
                'database_name' => $tenantDb,
                'credential_reference' => str_ends_with($tenantDb, '_test') ? 'test' : 'local',
                'placement_type' => 'shared',
                'status' => 'active',
            ]);
        }

        $tenant = Tenant::query()->firstWhere('code', $fixtureCode);

        if ($tenant === null) {
            $tenant = Tenant::query()->create([
                'public_id' => (string) Str::ulid(),
                'code' => $fixtureCode,
                'name' => 'Local Tenant',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'metadata' => ['domains' => ['localhost']],
            ]);
        }

        $placement = TenantPlacement::query()->firstWhere('tenant_id', $tenant->row_id);

        if ($placement === null) {
            $placement = TenantPlacement::query()->create([
                'tenant_id' => $tenant->row_id,
                'shard_id' => $shard->row_id,
                'status' => 'active',
                'placed_at' => now(),
            ]);
        }

        $user = User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => 'Account Test User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'initial_username',
            'password' => self::CURRENT_PASSWORD,
            'status' => 'active',
        ], $attributes));

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        if ($fixtureWasCreated) {
            Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/shard',
                '--force' => true,
            ]);
            Artisan::call('tenancy:sync-registry', ['--shard' => $fixtureCode]);
        }

        config(['tenancy.local_tenant' => $fixtureCode]);
        app(TenantContext::class)->initialize($tenant, $placement, $shard);

        return $user;
    }
}
