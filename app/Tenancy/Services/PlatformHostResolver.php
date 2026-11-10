<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

/**
 * Detects hosts that belong to the central platform instead of a tenant site.
 *
 * Two levels are used:
 *  - isPlatformHost(): any host that must never be treated as a tenant host,
 *    including local development hosts and APP_URL.
 *  - isEnforcedPlatformHost(): only explicitly configured platform domains
 *    (SITE_PLATFORM_HOSTS / APP_URL), excluding local development hosts.
 *    Used to enforce the separation between the central login and tenant
 *    logins without breaking local development.
 */
final class PlatformHostResolver
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'];

    public function isPlatformHost(string $host): bool
    {
        $normalizedHost = $this->normalize($host);
        if ($normalizedHost === '') {
            return false;
        }

        if (in_array($normalizedHost, self::LOCAL_HOSTS, true)) {
            return true;
        }

        return $this->isEnforcedPlatformHost($normalizedHost);
    }

    public function isEnforcedPlatformHost(string $host): bool
    {
        $normalizedHost = $this->normalize($host);
        if ($normalizedHost === '') {
            return false;
        }

        // Local development hosts are intentionally exempt. They are shared by
        // every tenant in local setups, so host-based tenant separation would
        // block normal development and test flows.
        if (in_array($normalizedHost, self::LOCAL_HOSTS, true)) {
            return false;
        }

        $appHost = $this->normalize((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));
        if ($appHost !== '' && $appHost === $normalizedHost) {
            return true;
        }

        return collect(explode(',', (string) config('site.platform_hosts', '')))
            ->map(fn (string $platformHost): string => $this->normalize($platformHost))
            ->contains($normalizedHost);
    }

    public function normalize(string $host): string
    {
        return strtolower(trim(explode(':', trim($host))[0]));
    }
}
