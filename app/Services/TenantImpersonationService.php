<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantImpersonationToken;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class TenantImpersonationService
{
    /**
     * @return array{token: TenantImpersonationToken, redirect_url: string, target_user: User, tenant: Tenant, domain: ?string}
     */
    public function generateToken(
        Tenant $tenant,
        ?User $targetUser,
        User $impersonator,
        ?string $domain = null,
        ?Request $request = null,
    ): array {
        if ($impersonator->is_superadmin !== true) {
            throw new AccessDeniedHttpException('Hanya Superadmin yang memiliki izin untuk auto-login / impersonasi tenant.');
        }

        if (! in_array($tenant->status, ['active', 'read_only'], true)) {
            throw new DomainException("Tenant [{$tenant->name}] tidak aktif (status: {$tenant->status}).");
        }

        if ($targetUser === null) {
            $targetUser = User::query()
                ->where('tenant_id', $tenant->row_id)
                ->where('status', 'active')
                ->orderBy('row_id')
                ->first();
        }

        if ($targetUser === null) {
            throw new DomainException("Tidak ada pengguna aktif yang ditemukan untuk tenant [{$tenant->name}]. Silakan tambahkan pengguna terlebih dahulu.");
        }

        if ((int) $targetUser->tenant_id !== (int) $tenant->row_id) {
            throw new DomainException("Pengguna [{$targetUser->name}] bukan merupakan anggota tenant [{$tenant->name}].");
        }

        if ($targetUser->status !== 'active') {
            throw new DomainException("Pengguna [{$targetUser->name}] berstatus non-aktif ({$targetUser->status}).");
        }

        $rawToken = Str::random(64);

        $tokenRecord = TenantImpersonationToken::query()->create([
            'token' => $rawToken,
            'tenant_id' => $tenant->row_id,
            'user_id' => $targetUser->row_id,
            'impersonator_id' => $impersonator->row_id,
            'expires_at' => now()->addMinutes(5),
        ]);

        $baseUrl = $this->resolveTenantBaseUrl($tenant, $domain, $request);
        $redirectUrl = rtrim($baseUrl, '/').'/auth/impersonate/'.$rawToken;

        return [
            'token' => $tokenRecord,
            'redirect_url' => $redirectUrl,
            'target_user' => $targetUser,
            'tenant' => $tenant,
            'domain' => $domain,
        ];
    }

    /**
     * @return array{token: TenantImpersonationToken, user: User, tenant: Tenant, impersonator: User}
     */
    public function consumeToken(string $token): array
    {
        /** @var TenantImpersonationToken|null $record */
        $record = TenantImpersonationToken::query()
            ->with(['tenant', 'user', 'impersonator'])
            ->where('token', $token)
            ->first();

        if ($record === null || ! $record->isValid()) {
            throw new DomainException('Token auto-login tidak valid atau telah kedaluwarsa.');
        }

        $record->forceFill(['used_at' => now()])->save();

        $tenant = $record->tenant;
        $user = $record->user;
        $impersonator = $record->impersonator;

        if ($tenant === null || ! in_array($tenant->status, ['active', 'read_only'], true)) {
            throw new DomainException('Tenant tidak aktif atau ditangguhkan.');
        }

        if ($user === null || $user->status !== 'active') {
            throw new DomainException('Pengguna tenant tidak aktif.');
        }

        if ($impersonator === null || $impersonator->is_superadmin !== true) {
            throw new AccessDeniedHttpException('Sesi impersonator tidak sah.');
        }

        return [
            'token' => $record,
            'user' => $user,
            'tenant' => $tenant,
            'impersonator' => $impersonator,
        ];
    }

    public function resolveTenantBaseUrl(Tenant $tenant, ?string $domain = null, ?Request $request = null): string
    {
        $chosenDomain = null;

        if ($domain !== null && trim($domain) !== '') {
            $chosenDomain = strtolower(trim($domain));
        } else {
            $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
            $domains = $metadata['domains'] ?? ($metadata['domain'] ?? []);
            $domains = is_array($domains) ? $domains : (trim((string) $domains) !== '' ? [$domains] : []);
            $customDomains = array_values(array_filter($domains));

            if (! empty($customDomains)) {
                $chosenDomain = (string) $customDomains[0];
            }
        }

        if ($chosenDomain !== null) {
            $clean = preg_replace('#^https?://#', '', $chosenDomain);
            $clean = rtrim((string) $clean, '/');

            $scheme = 'https';
            if ($request !== null) {
                $scheme = $request->getScheme();
            } elseif (app()->environment('local', 'testing')) {
                $scheme = 'http';
            }

            if (str_contains($clean, 'localhost') || str_contains($clean, '127.0.0.1')) {
                $scheme = 'http';
            }

            return "{$scheme}://{$clean}";
        }

        if ($request !== null) {
            return $request->getSchemeAndHttpHost();
        }

        return (string) config('app.url', 'http://localhost');
    }
}
