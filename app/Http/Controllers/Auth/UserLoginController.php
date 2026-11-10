<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Models\UserRole;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Tenancy\Services\PlatformHostResolver;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Passwordless login switcher.
 *
 * There are two separate scopes and they must never mix:
 *  - central (/user)      : platform users only (superadmin / province / regency)
 *  - tenant  (/login/user): users owned by the tenant resolved from the host
 *
 * The tenant scope resolves the location automatically from the current host,
 * so the card list only ever contains that location's users.
 */
final class UserLoginController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PlatformHostResolver $platformHosts,
        private readonly TenantResolver $tenants,
    ) {}

    /**
     * Central/platform switcher: only users that AuthController would accept on
     * a platform host (superadmin, province, and regency supervisors).
     */
    public function indexCentral(Request $request): Response|RedirectResponse
    {
        $this->ensureEnabled();

        // A tenant host should use the automatic tenant page. Unknown hosts
        // must not fall back to the central list, otherwise tenant domains
        // could expose platform users.
        if (! $this->platformHosts->isPlatformHost($request->getHost())) {
            try {
                $this->tenants->resolveByHost($request->getHost(), $request->user());

                return redirect()->route('login.tenant-users');
            } catch (Throwable) {
                abort(404, 'Tenant tidak dikenali dari alamat ini.');
            }
        }

        $users = User::query()
            ->where('status', '!=', 'deleted')
            ->where(function ($query): void {
                $query->where('is_superadmin', true)
                    ->orWhere('is_regency_user', true)
                    ->orWhere('is_province_user', true);
            })
            ->orderBy('name')
            ->get($this->userColumns());

        return Inertia::render('Auth/UserLogin', [
            'scope' => 'central',
            'context' => [
                'code' => 'PUSAT',
                'name' => 'Platform Pusat',
                'host' => $request->getHost(),
                'label' => 'Pusat / Platform',
            ],
            'users' => $this->payload($users, null, $request),
        ]);
    }

    /**
     * Tenant location switcher: users that belong to the tenant resolved from
     * the current host (automatic), or from the optional {tenant} URL segment.
     */
    public function indexTenant(Request $request): Response
    {
        $this->ensureEnabled();
        $tenant = $this->tenantContext->tenant();

        $users = $this->tenantUsersQuery($tenant)
            ->orderBy('name')
            ->get($this->userColumns());

        return Inertia::render('Auth/UserLogin', [
            'scope' => 'tenant',
            'context' => [
                'code' => $tenant->code,
                'name' => $tenant->name,
                'host' => $request->getHost(),
                'label' => 'Lokasi Tenant',
            ],
            'users' => $this->payload($users, $tenant, $request),
        ]);
    }

    public function loginCentral(Request $request, User $user): RedirectResponse
    {
        $this->ensureEnabled();

        abort_unless($this->isCentralUser($user), 404, 'Akun tenant tidak dapat login dari halaman pusat.');
        abort_unless($user->status === 'active', 403, 'Akun ini sedang tidak aktif.');

        $this->signIn($request, $user);

        $redirect = match (true) {
            $user->is_superadmin === true => route('admin.dashboard'),
            $user->isProvinceUser() => route('province.dashboard'),
            $user->isRegencyUser() => route('regency.dashboard'),
            default => route('dashboard'),
        };

        return redirect()
            ->to($redirect)
            ->with('success', "Login otomatis sebagai {$user->name} (Pusat).");
    }

    /**
     * @param  string  $tenantCode  Route parameter; kept so the controller
     *                              arguments stay aligned with the URL params.
     */
    public function loginTenant(Request $request, string $tenantCode, User $user): RedirectResponse
    {
        $this->ensureEnabled();
        $tenant = $this->tenantContext->tenant();

        // Never let this endpoint escape the tenant resolved from the URL.
        abort_unless((int) $user->tenant_id === (int) $tenant->row_id, 404);
        abort_if($this->isCentralUser($user), 404, 'Akun pusat tidak dapat login dari halaman ini.');
        abort_unless($user->status === 'active', 403, 'Akun ini sedang tidak aktif.');

        $hasActiveMembership = TenantMembership::query()
            ->where('tenant_id', $tenant->row_id)
            ->where('user_id', $user->row_id)
            ->where('status', 'active')
            ->exists();

        abort_unless($hasActiveMembership, 403, 'Akun ini tidak terhubung aktif ke tenant ini.');

        $this->signIn($request, $user);

        return redirect()
            ->route('dashboard')
            ->with('success', "Login otomatis sebagai {$user->name} ({$tenant->name}).");
    }

    /**
     * Auto-login for the automatic tenant URL (/login/user/{user}); the tenant
     * is resolved from the host by the tenant middleware.
     */
    public function loginTenantAuto(Request $request, User $user): RedirectResponse
    {
        return $this->loginTenant($request, '', $user);
    }

    private function signIn(Request $request, User $user): void
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget([
            'impersonated_by',
            'impersonator_name',
            'impersonated_tenant_id',
            'impersonated_at',
        ]);

        // Keep the session validator in sync (same behaviour as AuthController).
        $guard = Auth::guard('web');
        $passwordHash = $user->fresh()?->getAuthPassword();
        if ($passwordHash !== null) {
            if (method_exists($guard, 'hashPasswordForCookie')) {
                $passwordHash = $guard->hashPasswordForCookie($passwordHash);
            }
            $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $passwordHash);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<array<string, mixed>>
     */
    private function payload(Collection $users, ?Tenant $tenant, Request $request): array
    {
        $userIds = $users->pluck('row_id')->map(fn ($id) => (int) $id)->all();

        $roleMap = [];
        if ($tenant !== null && $userIds !== []) {
            $roleMap = UserRole::query()
                ->whereIn('platform_user_id', $userIds)
                ->with('role:row_id,code,name')
                ->get()
                ->mapWithKeys(fn (UserRole $userRole): array => [
                    (int) $userRole->platform_user_id => [
                        'code' => $userRole->role?->code,
                        'name' => $userRole->role?->name,
                    ],
                ])
                ->all();
        }

        $membershipMap = $tenant !== null
            ? TenantMembership::query()
                ->where('tenant_id', $tenant->row_id)
                ->get(['user_id', 'status'])
                ->keyBy('user_id')
            : collect();

        return $users->map(function (User $user) use ($roleMap, $membershipMap, $tenant, $request): array {
            $membership = $membershipMap->get((int) $user->row_id);
            $hasActiveMembership = $membership !== null && $membership->status === 'active';
            $isActive = $user->status === 'active';

            return [
                'row_id' => (int) $user->row_id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'role_code' => $roleMap[(int) $user->row_id]['code'] ?? $this->centralRoleCode($user),
                'role_name' => $roleMap[(int) $user->row_id]['name'] ?? $this->centralRoleName($user),
                'is_village_user' => (bool) $user->is_village_user,
                'photo_url' => $user->photo_path
                    ? asset('storage/'.ltrim((string) $user->photo_path, '/')).'?v='.($user->updated_at?->timestamp ?? time())
                    : null,
                'last_login_at' => $user->last_login_at?->toDateTimeString(),
                'can_login' => $isActive && ($tenant === null || $hasActiveMembership),
                'is_self' => (int) $user->row_id === (int) $request->user()?->row_id,
            ];
        })->values()->all();
    }

    /** @return Builder<User> */
    private function tenantUsersQuery(Tenant $tenant)
    {
        return User::query()
            ->where('tenant_id', $tenant->row_id)
            ->where('status', '!=', 'deleted')
            ->whereNotIn('is_superadmin', [true])
            ->whereNotIn('is_regency_user', [true])
            ->whereNotIn('is_province_user', [true]);
    }

    private function isCentralUser(User $user): bool
    {
        return $user->is_superadmin === true
            || $user->isProvinceUser()
            || $user->isRegencyUser();
    }

    private function centralRoleCode(User $user): ?string
    {
        return match (true) {
            $user->is_superadmin === true => 'superadmin',
            $user->isProvinceUser() => 'province_supervisor',
            $user->isRegencyUser() => 'regency_supervisor',
            default => null,
        };
    }

    private function centralRoleName(User $user): ?string
    {
        return match (true) {
            $user->is_superadmin === true => 'Superadmin Platform',
            $user->isProvinceUser() => 'Supervisor Provinsi',
            $user->isRegencyUser() => 'Supervisor Kabupaten',
            default => null,
        };
    }

    /** @return list<string> */
    private function userColumns(): array
    {
        return [
            'row_id',
            'name',
            'username',
            'email',
            'phone',
            'status',
            'photo_path',
            'updated_at',
            'last_login_at',
            'is_village_user',
            'is_superadmin',
            'is_regency_user',
            'is_province_user',
        ];
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('quick_login.enabled', false), 404);
    }
}
