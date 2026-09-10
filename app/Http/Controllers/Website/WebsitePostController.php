<?php

declare(strict_types=1);

namespace App\Http\Controllers\Website;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Website\Models\SitePost;
use App\Http\Requests\Website\SitePostRequest;
use App\Tenancy\Services\TenantSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class WebsitePostController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'website.view');

        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = $this->sort((string) $request->query('sort', 'published_at'));
        $direction = $this->direction((string) $request->query('direction', 'desc'));

        $posts = SitePost::query()
            ->withTrashed()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SitePost $post): array => [
                'row_id' => $post->row_id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'published_at' => $post->published_at?->toIso8601String(),
                'cover_image_path' => $post->cover_image_path,
                'deleted_at' => $post->deleted_at?->toIso8601String(),
            ]);

        return Inertia::render('Website/Posts/Index', [
            'posts' => $posts,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'website.manage');

        return Inertia::render('Website/Posts/Form', ['post' => null]);
    }

    public function store(SitePostRequest $request, TenantSequenceService $sequences): RedirectResponse
    {
        $attributes = $this->persistedAttributes($request, null);

        $post = DB::connection('tenant')->transaction(fn () => SitePost::query()->create([
            ...$attributes,
            'author_name' => $request->user()?->name,
            'id' => $sequences->next('site_posts'),
        ]));

        $this->storeCoverImage($request, $post);

        return to_route('website.posts.index')->with('success', 'Berita berhasil ditambahkan.');
    }

    public function edit(Request $request, SitePost $post): Response
    {
        $this->permissions->denyUnless($request->user(), 'website.manage');

        return Inertia::render('Website/Posts/Form', [
            'post' => [
                'row_id' => $post->row_id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'status' => $post->status,
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'meta_description' => $post->meta_description,
                'cover_image_path' => $post->cover_image_path,
            ],
        ]);
    }

    public function update(SitePostRequest $request, SitePost $post): RedirectResponse
    {
        $post->update($this->persistedAttributes($request, $post));

        $this->storeCoverImage($request, $post);

        return to_route('website.posts.index')->with('success', 'Berita berhasil diperbarui.');
    }

    public function destroy(Request $request, SitePost $post): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'website.manage');

        $post->delete();

        return to_route('website.posts.index')->with('success', 'Berita dipindahkan ke sampah.');
    }

    public function restore(Request $request, int $postId): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'website.manage');

        $post = SitePost::withTrashed()->findOrFail($postId);
        $post->restore();

        return to_route('website.posts.index')->with('success', 'Berita dipulihkan.');
    }

    public function removeCover(Request $request, SitePost $post): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'website.manage');

        if (is_string($post->cover_image_path) && $post->cover_image_path !== '') {
            Storage::disk('public')->delete($post->cover_image_path);
            $post->update(['cover_image_path' => null]);
        }

        return back()->with('success', 'Gambar sampul dihapus.');
    }

    /**
     * Slug auto-generated from the title when left blank; publish date stamped
     * on first publish so drafts keep their original publish timestamp when
     * edited after going live.
     *
     * @return array<string, mixed>
     */
    private function persistedAttributes(SitePostRequest $request, ?SitePost $post): array
    {
        $validated = $request->validated();

        $slug = (string) ($validated['slug'] ?? '');
        if ($slug === '') {
            // Keep the existing slug on edits (stable public URLs); only
            // generate for new rows or when the author clears the field.
            $slug = $post?->slug ?? $this->uniqueSlug($this->slugify((string) $validated['title']), $post?->row_id);
        }

        $publishedAt = $validated['published_at'] ?? null;
        if ($validated['status'] === 'published') {
            $publishedAt ??= $post?->published_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
        } else {
            $publishedAt = null;
        }

        return [
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'excerpt' => $validated['excerpt'] ?? null,
            'status' => $validated['status'],
            'published_at' => $publishedAt,
            'meta_description' => $validated['meta_description'] ?? null,
        ];
    }

    private function storeCoverImage(SitePostRequest $request, SitePost $post): void
    {
        if (! $request->hasFile('cover_image')) {
            return;
        }

        $file = $request->file('cover_image');
        $path = $file->storeAs("site/posts/{$post->id}", 'cover.'.$file->getClientOriginalExtension(), 'public');

        if (is_string($post->cover_image_path) && $post->cover_image_path !== $path) {
            Storage::disk('public')->delete($post->cover_image_path);
        }

        $post->update(['cover_image_path' => $path]);
    }

    private function slugify(string $title): string
    {
        $ascii = Str::ascii(Str::transliterate($title));

        return trim(Str::lower(preg_replace('/[^A-Za-z0-9]+/', '-', $ascii) ?? ''), '-') ?: 'berita';
    }

    private function uniqueSlug(string $base, ?int $ignoreRowId): string
    {
        $slug = $base;
        $suffix = 2;

        while (SitePost::query()
            ->when($ignoreRowId !== null, fn ($query) => $query->where('row_id', '!=', $ignoreRowId))
            ->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [15, 30, 50, 100], true) ? $value : 15;
    }

    private function sort(string $value): string
    {
        return [
            'title' => 'title',
            'status' => 'status',
            'published_at' => 'published_at',
        ][$value] ?? 'published_at';
    }

    private function direction(string $value): string
    {
        return in_array($value, ['asc', 'desc'], true) ? $value : 'desc';
    }
}
