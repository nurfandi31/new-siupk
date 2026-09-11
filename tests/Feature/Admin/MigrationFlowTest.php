<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\CutoverRun;
use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MigrationFlowTest extends TestCase
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

        Cache::clear();
        config([
            'database.connections.legacy' => array_merge(config('database.connections.tenant'), [
                'name' => 'legacy',
                'database' => config('database.connections.platform.database'),
            ]),
        ]);
        \DB::purge('legacy');
        $this->createLegacyFixture();
    }

    public function test_legacy_tenants_endpoint_returns_next_tenant_enrichment(): void
    {
        $tenant = $this->createTenant('320101', 'existing');

        $response = $this->actingAs($this->superadmin())
            ->getJson('/admin/migration/legacy-tenants');

        $response->assertOk()
            ->assertJsonCount(2, 'legacy_tenants')
            ->assertJsonPath('count', 2)
            ->assertJsonPath('legacy_tenants.0.legacy_id', 101)
            ->assertJsonPath('legacy_tenants.0.legacy_name', 'Bantul')
            ->assertJsonPath('legacy_tenants.0.legacy_code', '320101')
            ->assertJsonPath('legacy_tenants.0.next_tenant.row_id', $tenant->row_id)
            ->assertJsonPath('legacy_tenants.1.next_tenant', null);
    }

    public function test_store_via_legacy_id_resolves_suffix_and_mapped_tenant(): void
    {
        $tenant = $this->createTenant('320101', 'existing');
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)
            ->post('/admin/migrations', [
                'legacy_id' => 101,
                'is_dry_run' => true,
            ]);

        $response->assertRedirect();

        $run = CutoverRun::query()->sole();
        self::assertSame($tenant->row_id, (int) $run->tenant_id);
        self::assertSame('101', $run->suffix);
        self::assertSame(101, $run->options['legacy_id']);
        self::assertSame('Bantul', $run->options['legacy_name']);
        self::assertFalse($run->options['auto_provisioned']);
        self::assertSame($admin->row_id, $run->options['created_by_user_id']);
    }

    public function test_store_auto_provisions_tenant_with_district_code(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/migrations', [
                'legacy_id' => 102,
                'auto_provision' => true,
                'is_dry_run' => true,
            ])
            ->assertRedirect();

        $run = CutoverRun::query()->sole();
        $tenant = Tenant::query()->where('district_code', '320102')->sole();

        self::assertSame($tenant->row_id, (int) $run->tenant_id);
        self::assertSame('102', $run->suffix);
        self::assertTrue($run->options['auto_provisioned']);
        self::assertSame('Sleman', $tenant->name);
        self::assertSame('provisioning', $tenant->status);
    }

    public function test_store_without_next_tenant_and_auto_provision_fails(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/migrations', [
                'legacy_id' => 102,
                'auto_provision' => false,
            ])
            ->assertSessionHasErrors('tenant_id');

        self::assertSame(0, CutoverRun::query()->count());
    }

    public function test_expert_mode_still_accepts_explicit_tenant_and_suffix(): void
    {
        $tenant = $this->createTenant('999999', 'expert');

        $this->actingAs($this->superadmin())
            ->post('/admin/migrations', [
                'tenant_id' => $tenant->row_id,
                'suffix' => 76,
                'chunk' => 250,
                'skip_membership' => true,
                'is_dry_run' => true,
            ])
            ->assertRedirect();

        $run = CutoverRun::query()->sole();

        self::assertSame('76', $run->suffix);
        self::assertSame($tenant->row_id, (int) $run->tenant_id);
        self::assertSame(250, $run->options['chunk']);
        self::assertTrue($run->options['skip_membership']);
        self::assertArrayNotHasKey('legacy_id', $run->options);
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }

    private function createTenant(string $districtCode, string $suffix): Tenant
    {
        return Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-'.$suffix,
            'name' => 'Tenant '.$suffix,
            'district_code' => $districtCode,
            'status' => 'active',
        ]);
    }

    private function createLegacyFixture(): void
    {
        Schema::connection('legacy')->dropIfExists('kecamatan');
        Schema::connection('legacy')->create('kecamatan', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama_kec');
            $table->string('kd_kec', 6);
            $table->string('web_kec')->nullable();
        });

        \DB::connection('legacy')->table('kecamatan')->insert([
            ['id' => 101, 'nama_kec' => 'Bantul', 'kd_kec' => '320101', 'web_kec' => 'bantul.example.test'],
            ['id' => 102, 'nama_kec' => 'Sleman', 'kd_kec' => '320102', 'web_kec' => null],
        ]);
    }
}
