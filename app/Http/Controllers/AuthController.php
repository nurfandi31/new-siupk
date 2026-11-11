<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Tenancy\Services\PlatformHostResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController
{
    public function __construct(
        private readonly PlatformHostResolver $platformHosts,
    ) {}

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user?->is_superadmin === true) {
                return redirect()->route('admin.dashboard');
            }
            if ($user?->isRegencyUser()) {
                return redirect()->route('regency.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'centralLogin' => $this->platformHosts->isEnforcedPlatformHost($request->getHost()),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $identifier = (string) $request->validated('identifier');
        $password = (string) $request->validated('password');
        $remember = (bool) $request->boolean('remember');

        $user = User::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->where('status', 'active')
            ->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => 'Kredensial yang diberikan tidak valid.',
            ]);
        }

        // Domain pusat hanya untuk akun pusat. Akun tenant harus login lewat
        // domain tenant masing-masing agar tidak tercampur dengan panel pusat.
        if ($this->platformHosts->isEnforcedPlatformHost($request->getHost()) && $this->isTenantScopedUser($user)) {
            $domain = $this->tenantLoginDomain($user);

            throw ValidationException::withMessages([
                'identifier' => $domain !== null
                    ? "Akun ini adalah akun tenant. Silakan login melalui domain tenant: {$domain}."
                    : 'Akun ini adalah akun tenant. Silakan login melalui domain tenant yang terdaftar, bukan domain pusat.',
            ]);
        }

        if ($user->tenant_id === null && ! $user->is_superadmin && ! $user->isRegencyUser()) {
            $membership = $user->memberships()->where('status', 'active')->first();
            $user->forceFill(['tenant_id' => $membership?->tenant_id])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user, $remember);
        Auth::logoutOtherDevices($password);
        $request->session()->regenerate();

        $request->session()->flash('success', 'Login berhasil. Selamat datang kembali.');

        $guard = Auth::guard();
        $passwordHash = $user->fresh()?->getAuthPassword();
        if ($passwordHash !== null) {
            if (method_exists($guard, 'hashPasswordForCookie')) {
                $passwordHash = $guard->hashPasswordForCookie($passwordHash);
            }
            $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $passwordHash);
        }

        // Superadmin always lands on platform admin ? ignore intended(/admin)
        // left by a prior guest hit (would 403 non-superadmin, confuse superadmin flows).
        if ($user->is_superadmin === true) {
            $request->session()->forget('url.intended');

            return redirect()->route('admin.dashboard');
        }

        if ($user->isRegencyUser()) {
            $request->session()->forget('url.intended');

            return redirect()->route('regency.dashboard');
        }

        $intended = $request->session()->pull('url.intended');
        if (is_string($intended)) {
            if (
                str_contains(parse_url($intended, PHP_URL_PATH) ?? '', '/admin') ||
                str_contains(parse_url($intended, PHP_URL_PATH) ?? '', '/regency')
            ) {
                $intended = null;
            } elseif ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https' || str_starts_with((string) config('app.url'), 'https://')) {
                $intended = preg_replace('/^http:/i', 'https:', $intended);
            }
        }

        return redirect()->to($intended ?: route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->flash('success', 'Anda telah berhasil keluar.');

        return redirect()->route('login');
    }

    private function isTenantScopedUser(User $user): bool
    {
        return $user->is_superadmin !== true
            && ! $user->isRegencyUser()
            && ! $user->isProvinceUser();
    }

    private function tenantLoginDomain(User $user): ?string
    {
        $tenant = $this->tenantForUser($user);
        if ($tenant === null) {
            return null;
        }

        $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
        $domains = $metadata['domains'] ?? ($metadata['domain'] ?? []);
        $domains = is_array($domains) ? $domains : [$domains];

        foreach ($domains as $domain) {
            if (! is_string($domain)) {
                continue;
            }

            $clean = strtolower(trim($domain));
            $clean = preg_replace('#^https?://#', '', $clean);
            $clean = trim((string) explode('/', $clean)[0]);

            if ($clean !== '') {
                return $clean;
            }
        }

        return null;
    }

    private function tenantForUser(User $user): ?Tenant
    {
        if ($user->tenant_id !== null) {
            return Tenant::query()->find($user->tenant_id);
        }

        return $user->memberships()
            ->with('tenant')
            ->where('status', 'active')
            ->first()?->tenant;
    }
}
