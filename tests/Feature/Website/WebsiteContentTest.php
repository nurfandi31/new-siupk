<?php

declare(strict_types=1);

namespace Tests\Feature\Website;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Website\Models\SitePost;
use App\Models\Tenant\BusinessType;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\PublicSiteResolver;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class WebsiteContentTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);
        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(), 'tenant_id' => $this->testTenant->row_id,
            'name' => 'Website User', 'email' => 'website@example.test', 'username' => 'website_user',
            'password' => 'password', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_store_generates_slug_and_stamps_published_at(): void
    {
        $this->actingAs($this->user)
            ->post('/website/posts', [
                'title' => 'Berita Pembukaan Kantor',
                'content' => '<p>Isi berita.</p>',
                'status' => 'published',
            ])
            ->assertRedirect('/website/posts')
            ->assertSessionHas('success');

        $post = SitePost::query()->firstOrFail();
        self::assertSame('berita-pembukaan-kantor', $post->slug);
        self::assertSame('published', $post->status);
        self::assertNotNull($post->published_at);
        self::assertSame('Website User', $post->author_name);
    }

    public function test_store_draft_keeps_published_at_null(): void
    {
        $this->actingAs($this->user)->post('/website/posts', [
            'title' => 'Draf Pertama',
            'content' => '<p>Draf.</p>',
            'status' => 'draft',
        ])->assertRedirect('/website/posts');

        $post = SitePost::query()->firstOrFail();
        self::assertSame('draf-pertama', $post->slug);
        self::assertNull($post->published_at);
    }

    public function test_store_generates_unique_slug_suffix_for_same_title(): void
    {
        foreach (['Laporan Kegiatan', 'Laporan Kegiatan'] as $title) {
            $this->actingAs($this->user)->post('/website/posts', [
                'title' => $title,
                'content' => '<p>Isi.</p>',
                'status' => 'published',
            ])->assertRedirect('/website/posts');
        }

        self::assertEqualsCanonicalizing(
            ['laporan-kegiatan', 'laporan-kegiatan-2'],
            SitePost::query()->orderBy('row_id')->pluck('slug')->all(),
        );
    }

    public function test_store_rejects_duplicate_explicit_slug(): void
    {
        $payload = [
            'title' => 'Pengumuman',
            'slug' => 'pengumuman-umum',
            'content' => '<p>Isi.</p>',
            'status' => 'published',
        ];
        $this->actingAs($this->user)->post('/website/posts', $payload)->assertRedirect('/website/posts');

        $this->actingAs($this->user)->post('/website/posts', $payload)->assertSessionHasErrors('slug');
        self::assertSame(1, SitePost::query()->count());
    }

    public function test_update_keeps_existing_slug_and_publish_timestamp(): void
    {
        $this->actingAs($this->user)->post('/website/posts', [
            'title' => 'Judul Awal',
            'slug' => 'judul-awal',
            'content' => '<p>Isi.</p>',
            'status' => 'published',
        ]);
        $post = SitePost::query()->firstOrFail();
        $publishedAt = $post->published_at?->format('Y-m-d H:i:s');

        $this->actingAs($this->user)->put('/website/posts/'.$post->row_id, [
            'title' => 'Judul Baru',
            'slug' => '',
            'content' => '<p>Isi baru.</p>',
            'status' => 'published',
        ])->assertRedirect('/website/posts');

        $fresh = $post->fresh();
        self::assertSame('judul-awal', $fresh->slug);
        self::assertSame('Judul Baru', $fresh->title);
        self::assertSame($publishedAt, $fresh->published_at?->format('Y-m-d H:i:s'));
    }

    public function test_update_from_draft_to_published_stamps_date(): void
    {
        $this->actingAs($this->user)->post('/website/posts', [
            'title' => 'Masih Draf',
            'content' => '<p>Isi.</p>',
            'status' => 'draft',
        ]);
        $post = SitePost::query()->firstOrFail();
        self::assertNull($post->published_at);

        $this->actingAs($this->user)->put('/website/posts/'.$post->row_id, [
            'title' => 'Masih Draf',
            'content' => '<p>Isi.</p>',
            'status' => 'published',
        ])->assertRedirect('/website/posts');

        self::assertNotNull($post->fresh()->published_at);
    }

    public function test_destroy_soft_deletes_then_restore_recovers(): void
    {
        $this->actingAs($this->user)->post('/website/posts', [
            'title' => 'Akan Dihapus',
            'content' => '<p>Isi.</p>',
            'status' => 'published',
        ]);
        $post = SitePost::query()->firstOrFail();

        $this->actingAs($this->user)->delete('/website/posts/'.$post->row_id)->assertRedirect('/website/posts');
        self::assertNull(SitePost::query()->find($post->row_id));
        self::assertNotNull(SitePost::withTrashed()->find($post->row_id)?->deleted_at);

        $this->actingAs($this->user)->post('/website/posts/'.$post->row_id.'/restore')->assertRedirect('/website/posts');
        self::assertNotNull(SitePost::query()->find($post->row_id));
    }

    public function test_store_upload_then_remove_cover(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->post('/website/posts', [
            'title' => 'Berita Bergambar',
            'content' => '<p>Isi.</p>',
            'status' => 'published',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 200, 120),
        ])->assertRedirect('/website/posts');

        $post = SitePost::query()->firstOrFail();
        self::assertNotNull($post->cover_image_path);
        Storage::disk('public')->assertExists((string) $post->cover_image_path);

        $this->actingAs($this->user)->delete('/website/posts/'.$post->row_id.'/cover')->assertRedirect();
        self::assertNull($post->fresh()->cover_image_path);
        Storage::disk('public')->assertMissing((string) $post->cover_image_path);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/website/posts')->assertRedirect();
    }

    public function test_role_restricted_user_is_forbidden_from_website_admin(): void
    {
        $restricted = User::query()->create([
            'public_id' => (string) Str::ulid(), 'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir User', 'email' => 'kasir@example.test', 'username' => 'kasir_user',
            'password' => 'password', 'status' => 'active',
        ]);
        app(PermissionChecker::class)->assignRole($restricted, 'kasir');

        $this->actingAs($restricted)->get('/website/posts')->assertForbidden();
        $this->actingAs($restricted)->get('/website/pages')->assertForbidden();

        // Store/update are guarded through the FormRequest request_map entry
        // (which maps the concrete Site*Request classes, not the abstract base).
        $this->actingAs($restricted)->post('/website/pages', [
            'title' => 'Tentang Kami',
            'content' => '<p>Profil.</p>',
            'status' => 'published',
        ])->assertForbidden();
        self::assertSame(0, DB::connection('tenant')->table('site_pages')->count());
    }

    public function test_public_blog_index_lists_published_posts_only(): void
    {
        $this->activateTenantDomain();
        $this->seedPublicPosts();

        $this->get('http://bumdes-sukamaju.test/berita')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/BlogIndex')
                ->where('posts.data.0.title', 'Laporan Tahunan')
                ->where('posts.total', 1)
                ->where('search', ''));
    }

    public function test_public_blog_search_filters_results(): void
    {
        $this->activateTenantDomain();
        $this->seedPublicPosts();

        $this->get('http://bumdes-sukamaju.test/berita?q=laporan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/BlogIndex')
                ->where('posts.total', 1));

        $this->get('http://bumdes-sukamaju.test/berita?q=tidak-ada-kabar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/BlogIndex')
                ->where('posts.total', 0)
                ->where('search', 'tidak-ada-kabar'));
    }

    public function test_public_blog_detail_renders_published_post_and_falls_back_for_missing(): void
    {
        $this->activateTenantDomain();
        $this->seedPublicPosts();

        $this->get('http://bumdes-sukamaju.test/berita/laporan-tahunan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/BlogPost')
                ->where('post.title', 'Laporan Tahunan')
                ->where('post.content', '<p>Isi laporan.</p>')
                ->where('post.author_name', 'Admin Desa'));

        // Draft slug falls back to the blog index, never a 404.
        $this->get('http://bumdes-sukamaju.test/berita/draf-rahasia')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PublicSite/BlogIndex'));
    }

    public function test_public_static_page_renders_and_unknown_slug_keeps_tenant_branding(): void
    {
        $this->activateTenantDomain();
        DB::connection('tenant')->table('site_pages')->insert($this->pageRow(1, [
            'slug' => 'tentang-kami',
            'title' => 'Tentang Kami',
            'content' => '<p>Profil lembaga.</p>',
            'status' => 'published',
        ]));
        DB::connection('tenant')->table('site_pages')->insert($this->pageRow(2, [
            'slug' => 'draf-halaman',
            'title' => 'Draf',
            'content' => '<p>Draf.</p>',
            'status' => 'draft',
        ]));

        $this->get('http://bumdes-sukamaju.test/p/tentang-kami')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/StaticPage')
                ->where('page.title', 'Tentang Kami')
                ->where('page.content', '<p>Profil lembaga.</p>'));

        // Unpublished slugs stay on the tenant's own landing page.
        $this->get('http://bumdes-sukamaju.test/p/draf-halaman')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PublicSite/TenantHome'));
    }

    public function test_site_content_is_excluded_from_desktop_outbox(): void
    {
        $outboxDb = database_path('site_outbox_test.sqlite');
        File::delete($outboxDb);
        File::put($outboxDb, '');
        Config::set('database.default', 'desktop_local');
        Config::set('database.connections.desktop_local', ['driver' => 'sqlite', 'database' => $outboxDb]);
        Schema::connection('desktop_local')->create('outbox', function ($table): void {
            $table->bigIncrements('id');
            $table->uuid('mutation_uuid');
            $table->string('table_name');
            $table->string('operation');
            $table->string('row_public_id');
            $table->json('payload');
            $table->dateTime('created_at');
            $table->dateTime('pushed_at')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
        });
        DB::purge('desktop_local');
        DB::reconnect('desktop_local');

        try {
            SitePost::query()->create([
                'title' => 'Excluded', 'slug' => 'excluded', 'content' => '<p>Isi.</p>', 'status' => 'draft',
            ]);
            SitePost::query()->firstOrFail()->delete();

            // A not-excluded tenant model still reaches the outbox (control).
            $type = new BusinessType(['code' => 'OBS', 'name' => 'Observed', 'is_active' => true]);
            $type->setAttribute('tenant_id', 1);
            $type->setAttribute('id', 12);
            $type->setConnection('tenant');
            $type->save();

            self::assertSame(0, DB::connection('desktop_local')->table('outbox')->where('table_name', 'site_posts')->count());
            self::assertSame(1, DB::connection('desktop_local')->table('outbox')->where('table_name', 'business_types')->count());
        } finally {
            File::delete($outboxDb);
        }
    }

    private function activateTenantDomain(): void
    {
        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();
        $this->clearTenantTestContext();
    }

    private function seedPublicPosts(): void
    {
        DB::connection('tenant')->table('site_posts')->insert([
            $this->postRow(1, [
                'slug' => 'laporan-tahunan',
                'title' => 'Laporan Tahunan',
                'excerpt' => 'Ringkasan laporan.',
                'content' => '<p>Isi laporan.</p>',
                'status' => 'published',
                'author_name' => 'Admin Desa',
                'published_at' => now()->subDay(),
            ]),
            $this->postRow(2, [
                'slug' => 'draf-rahasia',
                'title' => 'Draf Rahasia',
                'content' => '<p>Belum tayang.</p>',
                'status' => 'draft',
            ]),
            $this->postRow(3, [
                'slug' => 'masih-akan-tayang',
                'title' => 'Terjadwal',
                'content' => '<p>Jadwal.</p>',
                'status' => 'published',
                'published_at' => now()->addDay(),
            ]),
        ]);
    }

    private function postRow(int $id, array $overrides = []): array
    {
        return [
            'tenant_id' => $this->testTenant->row_id,
            'id' => $id,
            'slug' => 'contoh',
            'title' => 'Contoh',
            'excerpt' => null,
            'content' => '<p>Isi.</p>',
            'cover_image_path' => null,
            'status' => 'draft',
            'published_at' => null,
            'author_name' => null,
            'meta_description' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
            ...$overrides,
        ];
    }

    private function pageRow(int $id, array $overrides = []): array
    {
        return [
            'tenant_id' => $this->testTenant->row_id,
            'id' => $id,
            'slug' => 'contoh',
            'title' => 'Contoh',
            'content' => '<p>Isi.</p>',
            'status' => 'draft',
            'published_at' => null,
            'meta_description' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
            ...$overrides,
        ];
    }
}
