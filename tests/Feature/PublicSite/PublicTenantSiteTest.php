<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Tenancy\Services\PublicSiteResolver;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class PublicTenantSiteTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->clearTenantTestContext();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_platform_host_renders_vendor_home(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    }

    public function test_unknown_host_soft_falls_back_to_vendor_home(): void
    {
        $response = $this->get('http://unknown-domain.test/');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    }

    public function test_tenant_domain_renders_tenant_landing(): void
    {
        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();

        $response = $this->get('http://bumdes-sukamaju.test/');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/TenantHome')
                ->where('organization.name', 'Tenant A')
                ->where('tenant.code', 'tenant-a'));
    }

    public function test_tenant_landing_prefers_organization_profile_display_name(): void
    {
        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();

        DB::connection('tenant')->table('organization_profiles')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => 1,
            'legal_name' => 'BUMDesma Sukamaju Sejahtera',
            'short_name' => 'BUMDesma Sukamaju',
            'address' => 'Jl. Desa Sukamaju No. 1',
            'phone' => '081234567890',
            'email' => 'info@bumdes-sukamaju.test',
            'timezone' => 'Asia/Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('http://bumdes-sukamaju.test/');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/TenantHome')
                ->where('organization.name', 'BUMDesma Sukamaju')
                ->where('organization.legal_name', 'BUMDesma Sukamaju Sejahtera')
                ->where('organization.phone', '081234567890'));
    }

    public function test_suspended_tenant_falls_back_to_vendor_home(): void
    {
        $this->testTenant->forceFill([
            'status' => 'suspended',
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();

        $response = $this->get('http://bumdes-sukamaju.test/');

        // Suspended tenants are excluded from host matching entirely, so the
        // domain renders the vendor page rather than a branded but dead site.
        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    }

    public function test_desktop_client_header_redirects_to_login(): void
    {
        $response = $this->get('http://localhost/', ['X-Desktop-Client' => '1']);

        $response->assertRedirect(route('login'));
    }

    public function test_host_resolution_is_cached_until_flush(): void
    {
        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();

        $resolver = app(PublicSiteResolver::class);

        $this->assertSame($this->testTenant->row_id, $resolver->resolve('bumdes-sukamaju.test')?->row_id);

        // Second hit is served from cache (versioned key unchanged).
        $this->assertSame($this->testTenant->row_id, $resolver->resolve('bumdes-sukamaju.test')?->row_id);

        $this->testTenant->forceFill(['metadata' => ['domains' => ['other-domain.test']]])->save();

        // Stale until flush: cached entry still matches the old domain.
        $this->assertSame($this->testTenant->row_id, $resolver->resolve('bumdes-sukamaju.test')?->row_id);

        // Flush bumps the version: fresh lookup sees the new domain set.
        app(PublicSiteResolver::class)->flush();
        $this->assertNull($resolver->resolve('bumdes-sukamaju.test'));
        $this->assertSame($this->testTenant->row_id, $resolver->resolve('other-domain.test')?->row_id);
    }
}
