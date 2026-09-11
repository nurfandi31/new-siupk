<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\DatabaseShard;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ShardPageTest extends TestCase
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

        DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'host' => 'mysql',
            'database_name' => 'siupk_shard_test',
            'credential_reference' => 'test',
            'status' => 'active',
        ]);
    }

    public function test_superadmin_can_open_shards_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/shards')
            ->assertOk();
    }

    public function test_non_superadmin_cannot_access_shards(): void
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'User',
            'username' => 'tenantuser',
            'email' => 'user@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/shards')
            ->assertRedirect(route('login'));
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin_'.Str::lower(Str::random(6)),
            'email' => 'superadmin_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }
}
