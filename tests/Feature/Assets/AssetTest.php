<?php

declare(strict_types=1);

namespace Tests\Feature\Assets;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetCategory;
use App\Domain\Assets\Services\AssetService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class AssetTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Aset',
            'email' => 'aset@example.test',
            'username' => 'aset_user',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_index_seeds_categories_and_lists(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/assets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Assets/Index')
                ->has('categories')
                ->where('counts.total', 0)
            );

        self::assertGreaterThanOrEqual(5, AssetCategory::query()->count());
    }

    public function test_show_with_book_value(): void
    {
        $asset = app(AssetService::class)->create([
            'name' => 'Laptop Kasir',
            'asset_code' => 'AST-001',
            'purchased_at' => '2025-01-15',
            'quantity' => 1,
            'unit_cost' => 12000000,
            'useful_life_months' => 48,
            'status' => 'good',
        ], (int) $this->user->row_id);

        $this->actingAs($this->user)
            ->get('/accounting/assets/'.$asset->row_id.'?as_of=2026-01-15')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Assets/Show')
                ->where('asset.name', 'Laptop Kasir')
                ->where('asset.acquisition', 12000000)
            );

        $calc = app(AssetService::class)->bookValue(
            $asset,
            CarbonImmutable::parse('2026-01-15'),
        );
        // Jan 2025 → Jan 2026 = 13 bulan kepemilikan (inclusive)
        self::assertSame(13, $calc['age_months']);
        self::assertEqualsWithDelta(250000.0, $calc['monthly_depreciation'], 0.01);
        self::assertEqualsWithDelta(12000000 - 250000 * 13, $calc['book_value'], 1.0);
    }

    public function test_create_via_http_removed(): void
    {
        $this->actingAs($this->user)->get('/accounting/assets/create')->assertNotFound();
        $this->actingAs($this->user)->post('/accounting/assets', ['name' => 'X'])->assertMethodNotAllowed();
    }

    public function test_land_not_depreciated(): void
    {
        $asset = Asset::query()->create([
            'name' => 'Tanah Kantor',
            'purchased_at' => '2010-01-01',
            'quantity' => 1,
            'unit_cost' => 500000000,
            'useful_life_months' => 0,
            'status' => 'good',
        ]);

        $calc = app(AssetService::class)->bookValue($asset, CarbonImmutable::parse('2026-07-01'));
        self::assertEqualsWithDelta(500000000.0, $calc['book_value'], 0.01);
        self::assertSame(0.0, $calc['monthly_depreciation']);
    }

    public function test_status_change_records_history(): void
    {
        $asset = app(AssetService::class)->create([
            'name' => 'Printer',
            'quantity' => 1,
            'unit_cost' => 2000000,
            'useful_life_months' => 36,
            'purchased_at' => '2024-06-01',
            'status' => 'good',
        ], (int) $this->user->row_id);

        app(AssetService::class)->update($asset, [
            'name' => 'Printer',
            'quantity' => 1,
            'unit_cost' => 2000000,
            'useful_life_months' => 36,
            'purchased_at' => '2024-06-01',
            'status' => 'damaged',
            'status_notes' => 'Rusak head',
        ], (int) $this->user->row_id);

        $this->actingAs($this->user)
            ->get('/accounting/assets/'.$asset->row_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('asset.status', 'damaged')
                ->has('histories', 2)
            );
    }
}
