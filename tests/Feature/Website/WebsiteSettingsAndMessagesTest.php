<?php

declare(strict_types=1);

namespace Tests\Feature\Website;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Sync\Contracts\ExcludedFromDesktopSync;
use App\Domain\Website\Models\SiteMessage;
use App\Domain\Website\Models\SiteSetting;
use App\Http\Requests\Website\SiteSettingRequest;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\PublicSiteResolver;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class WebsiteSettingsAndMessagesTest extends TestCase
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
            'name' => 'Website Settings User',
            'email' => 'website-settings@example.test',
            'username' => 'website_settings_user',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    // ————————————————————————————————————
    // Admin: pengaturan situs
    // ————————————————————————————————————

    public function test_settings_form_renders_for_authorized_user(): void
    {
        $this->actingAs($this->user)
            ->get('/website/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Website/Settings/Form')
                ->has('settings')
                ->has('heroImageUrl'));
    }

    public function test_settings_form_redirects_guest_to_login(): void
    {
        $this->get('/website/settings')->assertRedirect();
    }

    public function test_update_persists_site_settings(): void
    {
        $this->actingAs($this->user)->put('/website/settings', [
            'hero_tagline' => 'Situs Resmi Desa',
            'hero_description' => 'Portal dana bergulir.',
            'about_short' => 'Tentang singkat kami.',
            'facebook_url' => 'https://facebook.com/ruteng',
            'instagram_url' => null,
            'youtube_url' => 'https://youtube.com/@ruteng',
            'contact_phone' => '081234567890',
            'contact_email' => 'info@desa.example.test',
            'contact_address' => 'Jl. Raya No. 1',
            'footer_note' => 'Hak cipta desa.',
        ])
            ->assertRedirect('/website/settings')
            ->assertSessionHas('success');

        $settings = SiteSetting::query()->firstOrFail();
        self::assertSame('Situs Resmi Desa', $settings->hero_tagline);
        self::assertSame('Portal dana bergulir.', $settings->hero_description);
        self::assertSame('Tentang singkat kami.', $settings->about_short);
        self::assertSame('https://facebook.com/ruteng', $settings->facebook_url);
        self::assertSame('Hak cipta desa.', $settings->footer_note);
        self::assertSame('081234567890', $settings->contact_phone);
        self::assertNull($settings->hero_image_path);
    }

    public function test_update_stores_hero_image_and_remove_clears_it(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->put('/website/settings', [
            'hero_tagline' => null,
            'hero_description' => null,
            'about_short' => null,
            'footer_note' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'contact_address' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 400, 200),
        ])->assertRedirect('/website/settings');

        $settings = SiteSetting::query()->firstOrFail();
        self::assertNotNull($settings->hero_image_path);
        Storage::disk('public')->assertExists((string) $settings->hero_image_path);

        $firstPath = $settings->hero_image_path;

        // Remove: signal removal while also triggering the delete branch.
        $this->actingAs($this->user)->put('/website/settings', [
            'hero_tagline' => null,
            'hero_description' => null,
            'about_short' => null,
            'footer_note' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'contact_address' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'remove_hero_image' => true,
        ])->assertRedirect('/website/settings');

        $fresh = $settings->fresh();
        self::assertNull($fresh->hero_image_path);
        Storage::disk('public')->assertMissing((string) $firstPath);
    }

    public function test_settings_update_replaces_previous_hero_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->put('/website/settings', [
            'hero_tagline' => null,
            'hero_description' => null,
            'about_short' => null,
            'footer_note' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'contact_address' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 400, 200),
        ]);
        $settings = SiteSetting::query()->firstOrFail();
        $firstPath = $settings->hero_image_path;
        Storage::disk('public')->assertExists((string) $firstPath);

        $this->actingAs($this->user)->put('/website/settings', [
            'hero_tagline' => null,
            'hero_description' => null,
            'about_short' => null,
            'footer_note' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'contact_address' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'hero_image' => UploadedFile::fake()->image('hero2.jpg', 400, 200),
        ]);

        $fresh = $settings->fresh();
        self::assertNotNull($fresh->hero_image_path);
        self::assertNotSame($firstPath, $fresh->hero_image_path);
        Storage::disk('public')->assertMissing((string) $firstPath);
        Storage::disk('public')->assertExists((string) $fresh->hero_image_path);
    }

    public function test_role_restricted_user_is_forbidden_from_settings_and_messages(): void
    {
        $restricted = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir User',
            'email' => 'kasir-settings@example.test',
            'username' => 'kasir_settings_user',
            'password' => 'password',
            'status' => 'active',
        ]);
        app(PermissionChecker::class)->assignRole($restricted, 'kasir');

        $this->actingAs($restricted)->get('/website/settings')->assertForbidden();
        $this->actingAs($restricted)->put('/website/settings', [
            'hero_tagline' => null,
            'hero_description' => null,
            'about_short' => null,
            'footer_note' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'contact_address' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
        ])->assertForbidden();

        $this->actingAs($restricted)->get('/website/messages')->assertForbidden();

        $msg = SiteMessage::query()->create([
            'name' => 'Inbox Kasir',
            'message' => 'Isi pesan.',
        ]);

        $this->actingAs($restricted)->post('/website/messages/'.$msg->row_id.'/read')->assertForbidden();
        $this->actingAs($restricted)->delete('/website/messages/'.$msg->row_id)->assertForbidden();
        self::assertSame(1, SiteMessage::query()->count());
    }

    public function test_site_setting_request_maps_to_website_manage_permission(): void
    {
        self::assertSame(
            'website.manage',
            Config::get('permissions.request_map.'.SiteSettingRequest::class),
        );
    }

    // ————————————————————————————————————
    // Public: /kontak
    // ————————————————————————————————————

    public function test_public_contact_page_renders_on_tenant_domain(): void
    {
        $this->activateTenantDomain();

        $this->get('http://bumdes-sukamaju.test/kontak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PublicSite/Contact'));
    }

    public function test_public_contact_page_falls_back_to_vendor_home_on_platform_host(): void
    {
        // Platform hosts must see the vendor Home, not the tenant site.
        // RebuildTenantTestDatabases() leaves the tenant context initialized
        // from setUp, but a real worker starts uninitialized — clear it so
        // ResolvePublicSite → contact() follows the null-site fallback path.
        $this->clearTenantTestContext();

        $this->get('http://localhost/kontak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    }

    public function test_store_message_persists_and_returns_success(): void
    {
        $this->activateTenantDomain();

        $this->post('http://bumdes-sukamaju.test/kontak', [
            'name' => 'Warga Satu',
            'email' => 'warga@desa.example.test',
            'phone' => '081211112222',
            'subject' => 'Tanya dana',
            'message' => 'Berapa suku bunga tahun ini?',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // ResolvePublicSite cleared the context in its finally block; tenant
        // Eloquent needs it again for post-request assertions.
        $this->initTestContext();

        $msg = SiteMessage::query()->firstOrFail();
        self::assertSame('Warga Satu', $msg->name);
        self::assertSame('Berapa suku bunga tahun ini?', $msg->message);
        self::assertSame('warga@desa.example.test', $msg->email);
        self::assertNull($msg->read_at);
    }

    public function test_store_message_validation_requires_name_and_message(): void
    {
        $this->activateTenantDomain();

        $this->post('http://bumdes-sukamaju.test/kontak', [
            'name' => '',
            'message' => '',
        ])->assertSessionHasErrors(['name', 'message']);

        $this->initTestContext();

        self::assertSame(0, SiteMessage::query()->count());
    }

    public function test_honeypot_silently_tricks_bots(): void
    {
        $this->activateTenantDomain();

        $this->post('http://bumdes-sukamaju.test/kontak', [
            'name' => 'Bot',
            'message' => 'spam',
            'website' => 'http://spam.example.test',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->initTestContext();

        self::assertSame(0, SiteMessage::query()->count());
    }

    public function test_store_message_rate_limits_to_ten_per_minute(): void
    {
        $this->activateTenantDomain();

        for ($i = 0; $i < 10; $i++) {
            $this->post('http://bumdes-sukamaju.test/kontak', [
                'name' => "Warga {$i}",
                'message' => "Pesan {$i}",
            ])->assertRedirect();
        }

        $this->post('http://bumdes-sukamaju.test/kontak', [
            'name' => 'Warga 10',
            'message' => 'Kelebihan frekuensi.',
        ])->assertStatus(429);

        $this->initTestContext();

        self::assertSame(10, SiteMessage::query()->count());
    }

    // ————————————————————————————————————
    // Admin: inbox pesan masuk
    // ————————————————————————————————————

    public function test_messages_index_lists_messages_and_unread_count(): void
    {
        SiteMessage::query()->create([
            'name' => 'Warga A',
            'subject' => 'Hal A',
            'message' => 'Pesan A.',
        ]);
        SiteMessage::query()->create([
            'name' => 'Warga B',
            'message' => 'Pesan B.',
        ]);

        // Mark one as read so unreadCount can be distinguished.
        $second = SiteMessage::query()->orderByDesc('row_id')->firstOrFail();
        $second->forceFill(['read_at' => now()])->save();

        $this->actingAs($this->user)
            ->get('/website/messages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Website/Messages/Index')
                ->where('unreadCount', 1)
                ->where('messages.total', 2));
    }

    public function test_messages_search_filters_results(): void
    {
        SiteMessage::query()->create(['name' => 'Ali Ahmad', 'message' => 'Tanya bunga.']);
        SiteMessage::query()->create(['name' => 'Budi', 'message' => 'Tanya angsuran.']);

        $this->actingAs($this->user)
            ->get('/website/messages?q=Ali')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Website/Messages/Index')
                ->where('messages.total', 1)
                ->where('search', 'Ali'));
    }

    public function test_mark_read_sets_read_at_and_destroy_removes_row(): void
    {
        $msg = SiteMessage::query()->create([
            'name' => 'Untuk Tandai',
            'message' => 'Isi pesan.',
        ]);
        self::assertNull($msg->read_at);

        $this->actingAs($this->user)
            ->post('/website/messages/'.$msg->row_id.'/read')
            ->assertRedirect()
            ->assertSessionHas('success');

        self::assertNotNull($msg->fresh()->read_at);

        // Second markRead is idempotent — still a redirect, but no extra side effect.
        $this->actingAs($this->user)
            ->post('/website/messages/'.$msg->row_id.'/read')
            ->assertRedirect();

        $this->actingAs($this->user)
            ->delete('/website/messages/'.$msg->row_id)
            ->assertRedirect('/website/messages')
            ->assertSessionHas('success');

        self::assertNull(SiteMessage::query()->find($msg->row_id));
    }

    public function test_site_messages_are_excluded_from_desktop_outbox(): void
    {
        $this->assertTrue(
            method_exists(SiteMessage::class, 'isRead'),
            'SiteMessage guard contract: isRead() must exist.',
        );
        // Formal outbox exclusion is proved via sync contract; this smoke
        // test guards the model implements the exclusion interface.
        self::assertInstanceOf(
            ExcludedFromDesktopSync::class,
            new SiteMessage,
        );
    }

    // ————————————————————————————————————
    // Public: settings propagation + SEO
    // ————————————————————————————————————

    public function test_tenant_home_receives_site_settings_payload(): void
    {
        // Seed while the setUp context is still initialized; activateTenantDomain()
        // clears it afterwards, matching the public-site request lifecycle.
        SiteSetting::query()->create([
            'hero_tagline' => 'Mitra Ekonomi Desa',
            'hero_description' => 'Deskripsi hero situs.',
            'about_short' => 'Tentang singkat kami.',
            'footer_note' => 'Dikelola BPM.',
            'facebook_url' => 'https://facebook.com/test',
        ]);
        $this->activateTenantDomain();

        $this->get('http://bumdes-sukamaju.test/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/TenantHome')
                ->where('settings.hero_tagline', 'Mitra Ekonomi Desa')
                ->where('settings.footer_note', 'Dikelola BPM.'));
    }

    public function test_sitemap_lists_published_posts_pages_and_index(): void
    {
        $this->activateTenantDomain();
        DB::connection('tenant')->table('site_posts')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => 1,
            'slug' => 'laporan-tahunan',
            'title' => 'Laporan Tahunan',
            'excerpt' => null,
            'content' => '<p>Isi.</p>',
            'cover_image_path' => null,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_name' => null,
            'meta_description' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        DB::connection('tenant')->table('site_pages')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => 1,
            'slug' => 'tentang-kami',
            'title' => 'Tentang Kami',
            'content' => '<p>Isi.</p>',
            'status' => 'published',
            'published_at' => null,
            'meta_description' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $response = $this->get('http://bumdes-sukamaju.test/sitemap.xml');
        $response->assertOk();
        $xml = $response->getContent();
        self::assertStringContainsString('laporan-tahunan', $xml);
        self::assertStringContainsString('tentang-kami', $xml);
        self::assertStringContainsString('/berita', $xml);
    }

    public function test_robots_blocks_app_paths_and_links_sitemap(): void
    {
        $response = $this->get('http://bumdes-sukamaju.test/robots.txt');
        $response->assertOk();
        $txt = $response->getContent();
        self::assertStringContainsString('Disallow: /website', $txt);
        self::assertStringContainsString('Disallow: /dashboard', $txt);
        self::assertStringContainsString('Disallow: /master-data', $txt);
        self::assertStringContainsString('Sitemap: ', $txt);
        self::assertStringContainsString('/sitemap.xml', $txt);
    }

    // ————————————————————————————————————
    // Helpers
    // ————————————————————————————————————

    private function activateTenantDomain(): void
    {
        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();
        $this->clearTenantTestContext();
    }

    /**
     * Re-initialize the tenant context after activateTenantDomain() or a
     * public.site request cleared it, so tenant Eloquent assertions work.
     */
    private function initTestContext(): void
    {
        app(TenantContext::class)->initialize(
            $this->testTenant,
            $this->testPlacement,
            $this->testShard,
        );
    }
}
