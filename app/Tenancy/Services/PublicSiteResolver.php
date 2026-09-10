<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Models\Platform\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the tenant that owns the request host for public site rendering.
 *
 * Host resolution is on the hot path of every unauthenticated page view, so the
 * match is cached. The cache key embeds a version number that PublicSiteResolver::flush()
 * bumps, so invalidation after domain edits is O(1); superseded per-host entries
 * orphan and expire through their TTL (300 s) instead of needing a full flush.
 */
final class PublicSiteResolver
{
    public const CACHE_VERSION_KEY = 'public-site:host-version';

    public const CACHE_TTL_SECONDS = 300;

    private const RESERVED_HOSTS = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'];

    public function resolve(string $host): ?Tenant
    {
        $normalizedHost = $this->normalizeHost($host);

        if ($normalizedHost === '' || $this->isPlatformHost($normalizedHost)) {
            return null;
        }

        return Cache::remember(
            $this->cacheKey($normalizedHost),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): ?Tenant => $this->matchTenant($normalizedHost),
        );
    }

    public function flush(): void
    {
        $current = (int) Cache::get(self::CACHE_VERSION_KEY, 0);
        Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
    }

    public function normalizeHost(string $host): string
    {
        return strtolower(trim(explode(':', trim($host))[0]));
    }

    private function cacheKey(string $normalizedHost): string
    {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 0);

        return "public-site:host:v{$version}:{$normalizedHost}";
    }

    private function matchTenant(string $normalizedHost): ?Tenant
    {
        return Tenant::query()
            ->with(['placement.shard'])
            ->whereIn('status', ['active', 'read_only'])
            ->whereNotNull('metadata')
            ->orderByDesc('row_id')
            ->get()
            ->first(fn (Tenant $candidate): bool => $candidate->matchesHost($normalizedHost));
    }

    private function isPlatformHost(string $normalizedHost): bool
    {
        if (in_array($normalizedHost, self::RESERVED_HOSTS, true)) {
            return true;
        }

        $appHost = $this->normalizeHost((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));
        if ($appHost !== '' && $appHost === $normalizedHost) {
            return true;
        }

        return collect(explode(',', (string) config('site.platform_hosts', '')))
            ->map(fn (string $host): string => $this->normalizeHost($host))
            ->contains($normalizedHost);
    }
}
