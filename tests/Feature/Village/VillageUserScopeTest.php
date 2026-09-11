<?php

declare(strict_types=1);

namespace Tests\Feature\Village;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VillageUserScopeTest extends TestCase
{
    private User $villageOperator;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        if (! filter_var(env('RUN_TENANCY_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_TENANCY_INTEGRATION_TESTS=true to run tenancy tests.');
        }

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        $this->villageOperator = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Operator Desa Sukamaju',
            'username' => 'op_sukamaju_'.Str::random(4),
            'email' => 'op_sukamaju_'.Str::random(4).'@test.local',
            'password' => bcrypt('password'),
            'is_village_user' => true,
            'village_row_id' => 101,
            'status' => 'active',
        ]);

        $this->adminUser = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Admin Kecamatan',
            'username' => 'admin_kec_'.Str::random(4),
            'email' => 'admin_kec_'.Str::random(4).'@test.local',
            'password' => bcrypt('password'),
            'is_village_user' => false,
            'status' => 'active',
        ]);
    }

    public function test_village_user_helper_returns_true(): void
    {
        $this->assertTrue($this->villageOperator->isVillageUser());
        $this->assertFalse($this->adminUser->isVillageUser());
        $this->assertEquals(101, $this->villageOperator->village_row_id);
    }
}
