<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Website\Models\SiteMessage;
use App\Domain\Website\Models\SitePage;
use App\Domain\Website\Models\SitePost;
use App\Domain\Website\Models\SiteSetting;
use App\Http\Requests\PublicSite\SiteMessageRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class PublicSiteController
{
    /**
     * Public entry point. On a platform host (or an unknown host) this renders
     * the siupk marketing page; on a tenant's custom domain it renders the
     * tenant's own branded landing page so visitors see the organization that
     * owns the domain, not the vendor.
     */
    public function home(Request $request): Response|RedirectResponse
    {
        if (config('desktop.enabled') || $request->header('X-Desktop-Client') === '1') {
            return redirect()->route('login');
        }

        $context = app(TenantContext::class);

        // ResolvePublicSite clears the context in its finally block, so the
        // landing data is gathered here while the request is still inside it.
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', [
                'name' => config('app.name'),
                'status' => 'ok',
            ]);
        }

        $site['recent_posts'] = $this->resolveRecentPosts();
        $site['recent_pages'] = $this->resolveRecentPages();
        $site['contact'] = $this->resolveContactInfo();

        if ($this->isDemoTenant($site)) {
            $site['demo'] = [
                'login_url' => url('/login'),
            ];
        }

        return Inertia::render('PublicSite/TenantHome', $site);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveRecentPosts(): array
    {
        return SitePost::query()
            ->published()
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['row_id', 'slug', 'title', 'excerpt', 'cover_image_path', 'published_at'])
            ->map(fn (SitePost $post): array => [
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_image_url' => $post->cover_image_path !== null ? Storage::disk('public')->url($post->cover_image_path) : null,
                'published_at' => $post->published_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveRecentPages(): array
    {
        return SitePage::query()
            ->published()
            ->orderBy('slug')
            ->limit(6)
            ->get(['row_id', 'slug', 'title', 'meta_description'])
            ->map(fn (SitePage $page): array => [
                'slug' => $page->slug,
                'title' => $page->title,
                'excerpt' => $page->meta_description,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContactInfo(): array
    {
        $settings = SiteSetting::query()->first();

        return [
            'phone' => $settings?->contact_phone,
            'email' => $settings?->contact_email,
            'address' => $settings?->contact_address,
            'social' => [
                'facebook' => $settings?->facebook_url,
                'instagram' => $settings?->instagram_url,
                'youtube' => $settings?->youtube_url,
            ],
            'store_url' => route('public.contact.store'),
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function isDemoTenant(array $site): bool
    {
        $demoCode = trim((string) config('tenancy.demo_tenant_code', 'demo'));

        return $demoCode !== '' && (string) ($site['tenant']['code'] ?? '') === $demoCode;
    }

    /**
     * Public static-pages index for the resolved tenant domain. Pages are
     * curated content the admin maintains (profil, layanan, AD/ART, dll.)
     * and stay paginated like the blog index for consistency.
     */
    public function pages(Request $request): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $search = trim((string) $request->query('q', ''));

        $pages = SitePage::query()
            ->published()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('meta_description', 'like', "%{$search}%")))
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (SitePage $page): array => [
                'slug' => $page->slug,
                'title' => $page->title,
                'excerpt' => $page->meta_description,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('PublicSite/PagesIndex', [
            ...$site,
            'pages' => $pages,
            'search' => $search,
        ]);
    }

    /**
     * Public blog index for the resolved tenant domain. Platform hosts and
     * unknown hosts fall back to the vendor home so stray links never 404.
     */
    public function posts(Request $request): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $search = trim((string) $request->query('q', ''));

        $posts = SitePost::query()
            ->published()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (SitePost $post): array => [
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_image_url' => $post->cover_image_path !== null ? Storage::disk('public')->url($post->cover_image_path) : null,
                'published_at' => $post->published_at?->toIso8601String(),
            ]);

        return Inertia::render('PublicSite/BlogIndex', [
            ...$site,
            'posts' => $posts,
            'search' => $search,
        ]);
    }

    /**
     * Public blog post detail for the resolved tenant domain.
     */
    public function post(Request $request, string $slug): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $post = SitePost::query()->published()->where('slug', $slug)->first();

        if ($post === null) {
            return Inertia::render('PublicSite/BlogIndex', [
                ...$site,
                'posts' => SitePost::query()->published()->orderByDesc('published_at')->paginate(9),
                'search' => '',
            ]);
        }

        return Inertia::render('PublicSite/BlogPost', [
            ...$site,
            'post' => [
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'cover_image_url' => $post->cover_image_path !== null ? Storage::disk('public')->url($post->cover_image_path) : null,
                'published_at' => $post->published_at?->toIso8601String(),
                'author_name' => $post->author_name,
                'meta_description' => $post->meta_description,
            ],
        ]);
    }

    /**
     * Public static page detail for the resolved tenant domain.
     */
    public function page(Request $request, string $slug): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $page = SitePage::query()->published()->where('slug', $slug)->first();

        if ($page === null) {
            // Unknown slugs stay on the tenant's own branding; the vendor page
            // only belongs to platform hosts.
            return Inertia::render('PublicSite/TenantHome', $site);
        }

        return Inertia::render('PublicSite/StaticPage', [
            ...$site,
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'content' => $page->content,
                'meta_description' => $page->meta_description,
            ],
        ]);
    }

    private function shouldRedirectToVendor(Request $request): bool
    {
        return config('desktop.enabled') || $request->header('X-Desktop-Client') === '1';
    }

    /**
     * Public contact page for the resolved tenant domain.
     */
    public function contact(Request $request): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        return Inertia::render('PublicSite/Contact', [
            ...$site,
            'settings' => $this->resolveSettings(),
        ]);
    }

    /**
     * Store a public contact-form submission for the resolved tenant.
     * Rate-limited at the route level; a hidden honeypot field silently
     * drops obvious bot submissions.
     */
    public function storeMessage(SiteMessageRequest $request): RedirectResponse
    {
        $context = app(TenantContext::class);

        if (! $context->isInitialized()) {
            return redirect()->route('home');
        }

        $validated = $request->validated();

        // Honeypot: real users never see the "website" field. Pretend success
        // so bots do not learn they were caught.
        if (trim((string) ($validated['website'] ?? '')) !== '') {
            return redirect()->back()->with('success', 'Pesan berhasil dikirim. Terima kasih!');
        }

        SiteMessage::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
        ]);

        return redirect()->back()->with('success', 'Pesan berhasil dikirim. Terima kasih!');
    }

    /**
     * Sitemap for the resolved tenant domain; platform hosts get an empty
     * sitemap pointing at the vendor home only.
     */
    public function sitemap(Request $request): SymfonyResponse
    {
        $context = app(TenantContext::class);
        $urls = [url('/')];

        if ($context->isInitialized() && $context->tenant()->status !== 'suspended') {
            // Plain foreach: arrow functions capture $urls by value, so the
            // appends inside ->each() would be silently discarded.
            foreach (SitePost::query()->published()->orderByDesc('published_at')
                ->get(['slug', 'updated_at']) as $post) {
                $urls[] = route('public.post', $post->slug);
            }

            foreach (SitePage::query()->published()->orderBy('slug')
                ->get(['slug', 'updated_at']) as $page) {
                $urls[] = route('public.page', $page->slug);
            }

            $urls[] = route('public.posts');
            $urls[] = route('public.pages');
        }

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * robots.txt on tenant domains allows crawling of the public site but
     * never the authenticated app; platform hosts likewise block /website,
     * /dashboard and friends.
     */
    public function robots(Request $request): SymfonyResponse
    {
        $lines = [
            'User-agent: *',
            'Disallow: /login',
            'Disallow: /dashboard',
            'Disallow: /website',
            'Disallow: /master-data',
            'Disallow: /lending',
            'Disallow: /accounting',
            'Disallow: /settings',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('public.sitemap'),
        ];

        return response(implode("\n", $lines))->header('Content-Type', 'text/plain');
    }

    private function resolveSettings(): array
    {
        $settings = SiteSetting::query()->first();

        return [
            'hero_tagline' => $settings?->hero_tagline,
            'hero_description' => $settings?->hero_description,
            'hero_image_url' => $settings?->hero_image_path
                ? Storage::disk('public')->url($settings->hero_image_path)
                : null,
            'about_short' => $settings?->about_short,
            'social' => [
                'facebook' => $settings?->facebook_url,
                'instagram' => $settings?->instagram_url,
                'youtube' => $settings?->youtube_url,
            ],
            'contact_phone' => $settings?->contact_phone,
            'contact_email' => $settings?->contact_email,
            'contact_address' => $settings?->contact_address,
            'footer_note' => $settings?->footer_note,
        ];
    }

    /**
     * @return array<string, mixed>|null null when no tenant resolved for the host
     */
    private function resolveTenantSite(TenantContext $context): ?array
    {
        if (! $context->isInitialized()) {
            return null;
        }

        $tenant = $context->tenant();

        if ($tenant->status === 'suspended') {
            return null;
        }

        $profile = OrganizationProfile::query()->first();

        $displayName = $profile?->displayName() ?: (string) $tenant->name;

        return [
            'organization' => [
                'name' => $displayName,
                'legal_name' => $profile?->legal_name ?: (string) $tenant->name,
                'address' => $this->composeAddress($profile),
                'phone' => $profile?->phone,
                'email' => $profile?->email,
                'website' => $profile?->website,
                'logo_url' => $profile?->logo_url,
                'operational_start_year' => $profile?->operational_start_date?->year,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
            ],
            'tenant' => [
                'code' => $tenant->code,
                'is_training_mode' => $tenant->isTraining(),
            ],
            'settings' => $this->resolveSettings(),
            'stats' => $this->resolveTenantStats($tenant->row_id),
        ];
    }

    /**
     * Angka landing page diambil langsung dari basis data tenant (bukan dummy)
     * agar publik melihat kondisi riil koperasi/BUMDesma saat ini.
     *
     * @return array<string, int|float|null>
     */
    private function resolveTenantStats(int $tenantId): array
    {
        $today = now()->toDateString();
        $db = DB::connection('tenant');

        $membersActive = (int) $db->table('members')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $groupsActive = (int) $db->table('groups')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $loansActive = (int) $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'disbursed'])
            ->count();

        $loansPending = (int) $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'verified', 'waiting'])
            ->count();

        $loansTotal = (int) $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->count();

        $memberLoansActive = (int) $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->where('legacy_source', 'member_loan')
            ->whereIn('status', ['active', 'disbursed'])
            ->count();
        $groupLoansActive = (int) $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('legacy_source')->orWhere('legacy_source', '!=', 'member_loan');
            })
            ->whereIn('status', ['active', 'disbursed'])
            ->count();
        $memberLoansTotal = (int) $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->where('legacy_source', 'member_loan')
            ->count();
        $groupLoansTotal = (int) $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('legacy_source')->orWhere('legacy_source', '!=', 'member_loan');
            })
            ->count();

        $activeLoanIds = $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'disbursed'])
            ->pluck('row_id')
            ->all();

        $outstanding = 0.0;
        if ($activeLoanIds !== []) {
            $outstandingRow = $db->table('loan_installments')
                ->where('tenant_id', $tenantId)
                ->whereIn('loan_row_id', $activeLoanIds)
                ->selectRaw('COALESCE(SUM(CASE WHEN principal_due > principal_paid THEN principal_due - principal_paid ELSE 0 END), 0) AS total')
                ->first();
            $outstanding = (float) ($outstandingRow->total ?? 0);
        }

        // Kolektabilitas dihitung hanya dari angsuran yang sudah jatuh tempo.
        $dueAggregate = $db->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $today)
            ->selectRaw('COALESCE(SUM(principal_due + interest_due), 0) AS due_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN principal_paid > principal_due THEN principal_due ELSE principal_paid END + CASE WHEN interest_paid > interest_due THEN interest_due ELSE interest_paid END), 0) AS paid_total')
            ->first();

        $dueTotal = (float) ($dueAggregate->due_total ?? 0);
        $paidTotal = (float) ($dueAggregate->paid_total ?? 0);
        $collectionRate = $dueTotal > 0
            ? round(min(100.0, ($paidTotal / $dueTotal) * 100), 1)
            : null;

        // Ketepatan waktu dihitung dari kelompok angsuran yang sudah lunas.
        $paidRows = $db->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('paid_at')
            ->where(function ($query): void {
                $query->where('principal_paid', '>', 0)->orWhere('interest_paid', '>', 0);
            })
            ->get(['loan_row_id', 'installment_number', 'due_date', 'paid_at', 'principal_due', 'principal_paid', 'interest_due', 'interest_paid']);

        $paidGroups = $paidRows->groupBy(fn ($row): string => $row->loan_row_id.':'.$row->installment_number);
        $onTime = 0;
        $completedGroups = 0;
        foreach ($paidGroups as $rows) {
            $principalDue = (float) $rows->sum(fn ($row): float => (float) $row->principal_due);
            $principalPaid = (float) $rows->sum(fn ($row): float => (float) $row->principal_paid);
            $interestDue = (float) $rows->sum(fn ($row): float => (float) $row->interest_due);
            $interestPaid = (float) $rows->sum(fn ($row): float => (float) $row->interest_paid);

            if ($principalPaid + $interestPaid <= 0.005) {
                continue;
            }
            if ($principalPaid + 0.005 < $principalDue || $interestPaid + 0.005 < $interestDue) {
                continue;
            }

            $completedGroups++;
            $paidAt = (string) $rows->max('paid_at');
            $dueDate = (string) $rows->min('due_date');
            if ($paidAt !== '' && $dueDate !== '' && substr($paidAt, 0, 10) <= $dueDate) {
                $onTime++;
            }
        }
        $punctualityRate = $completedGroups > 0
            ? round(($onTime / $completedGroups) * 100, 1)
            : null;

        // Porsi & rata-rata nominal berdasarkan pinjaman yang sudah dicairkan.
        $disbursed = $db->table('loans')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('disbursed_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN legacy_source = 'member_loan' THEN principal_amount ELSE 0 END), 0) AS member_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN legacy_source = 'member_loan' THEN 0 ELSE principal_amount END), 0) AS group_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN legacy_source = 'member_loan' THEN 1 ELSE 0 END), 0) AS member_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN legacy_source = 'member_loan' THEN 0 ELSE 1 END), 0) AS group_count")
            ->first();

        $memberDisbursed = (float) ($disbursed->member_total ?? 0);
        $groupDisbursed = (float) ($disbursed->group_total ?? 0);
        $memberLoanCount = (int) ($disbursed->member_count ?? 0);
        $groupLoanCount = (int) ($disbursed->group_count ?? 0);
        $disbursedTotal = $memberDisbursed + $groupDisbursed;

        return [
            'members_active' => $membersActive,
            'groups_active' => $groupsActive,
            'loans_active' => $loansActive,
            'loans_pending' => $loansPending,
            'loans_total' => $loansTotal,
            'outstanding_principal' => round($outstanding, 2),
            'collection_rate' => $collectionRate,
            'punctuality_rate' => $punctualityRate,
            'group_share' => $disbursedTotal > 0 ? round(($groupDisbursed / $disbursedTotal) * 100, 1) : null,
            'member_share' => $disbursedTotal > 0 ? round(($memberDisbursed / $disbursedTotal) * 100, 1) : null,
            'group_average_principal' => $groupLoanCount > 0 ? round($groupDisbursed / $groupLoanCount, 2) : 0.0,
            'member_average_principal' => $memberLoanCount > 0 ? round($memberDisbursed / $memberLoanCount, 2) : 0.0,
            'group_loans_active' => $groupLoansActive,
            'member_loans_active' => $memberLoansActive,
            'group_loans_total' => $groupLoansTotal,
            'member_loans_total' => $memberLoansTotal,
            'assets_total' => (int) $db->table('assets')->where('tenant_id', $tenantId)->count(),
            'documents_total' => (int) $db->table('documents')->where('tenant_id', $tenantId)->count(),
            'loan_products_total' => (int) $db->table('loan_products')->where('tenant_id', $tenantId)->count(),
            'roles_total' => (int) $db->table('roles')->where('tenant_id', $tenantId)->count(),
            'fiscal_periods_total' => (int) $db->table('fiscal_periods')->where('tenant_id', $tenantId)->count(),
            'installments_total' => (int) $db->table('loan_installments')->where('tenant_id', $tenantId)->count(),
        ];
    }

    private function composeAddress(?OrganizationProfile $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $parts = array_filter([
            $profile->address,
            $profile->district_name,
            $profile->regency_name,
        ], fn (?string $part): bool => is_string($part) && trim($part) !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
