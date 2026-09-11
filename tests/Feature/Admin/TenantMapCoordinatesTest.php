<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantMapCoordinatesTest extends TestCase
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

    public function test_superadmin_can_update_tenant_map_coordinates(): void
    {
        $superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Superadmin Platform',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
            'is_superadmin' => true,
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'bumdesma-kedungreja',
            'name' => 'BUMDesma Kedungreja',
            'district_code' => '330101',
            'regency_code' => '3301',
            'regency_name' => 'Cilacap',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $response = $this->actingAs($superadmin)->put("/admin/tenants/{$tenant->row_id}", [
            'name' => 'BUMDesma Kedungreja Berkah',
            'status' => 'active',
            'district_code' => '330101',
            'map_latitude' => -7.585000,
            'map_longitude' => 108.798000,
            'map_zoom' => 14,
            'timezone' => 'Asia/Jakarta',
        ]);

        $response->assertRedirect("/admin/tenants/{$tenant->row_id}");

        $this->assertDatabaseHas('tenants', [
            'row_id' => $tenant->row_id,
            'map_latitude' => -7.585000,
            'map_longitude' => 108.798000,
            'map_zoom' => 14,
        ], 'platform');
    }

    public function test_validates_coordinate_out_of_regency_bounds(): void
    {
        $superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Superadmin Platform',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
            'is_superadmin' => true,
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'bumdesma-kedungreja',
            'name' => 'BUMDesma Kedungreja',
            'district_code' => '330101',
            'regency_code' => '3301', // Cilacap ~ (-7.53, 108.98)
            'regency_name' => 'Cilacap',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        // Pointing to Jayapura (-2.5, 140.7) while regency is Cilacap
        $response = $this->actingAs($superadmin)->put("/admin/tenants/{$tenant->row_id}", [
            'name' => 'BUMDesma Kedungreja Berkah',
            'status' => 'active',
            'district_code' => '330101',
            'map_latitude' => -2.5337,
            'map_longitude' => 140.7181,
            'map_zoom' => 14,
            'timezone' => 'Asia/Jakarta',
        ]);

        $response->assertSessionHasErrors(['map_latitude']);
    }
}
