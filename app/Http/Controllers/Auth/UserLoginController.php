<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Models\UserRole;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Passwordless tenant user switcher.
 *
 * Lists only the users that belong to the tenant resolved from the current
 * host, so demo/local tenants can sign in quickly by double-clicking a card.
 * Central users (superadmin, province & regency supervisors) are never listed
 * nor allowed to be signed in through this page.
 */
final class UserLoginController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->tenant();
        $this->ensureEnabled();

        $users = User::query()
            ->where('tenant_id', $tenant->row_id)
            ->where('status', '!=', 'deleted')
            ->whereNotIn('is_superadmin', [true])
            ->whereNotIn('is_regency_user', [true])
            ->whereNotIn('is_province_user', [true])
            ->orderBy('name')
            ->get([
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
            ]);

        $userIds = $users->pluck('row_id')->map(fn ($id) => (int) $id)->all();

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

        $membershipMap = TenantMembership::query()
            ->where('tenant_id', $tenant->row_id)
            ->get(['user_id', 'status'])
            ->keyBy('user_id');

        return Inertia::render('Auth/UserLogin', [
            'tenant' => [
                'row_id' => $tenant->row_id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'host' => $request->getHost(),
            ],
            'users' => $users->map(function (User $user) use ($roleMap, $membershipMap, $request): array {
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
                    'role_code' => $roleMap[(int) $user->row_id]['code'] ?? null,
                    'role_name' => $roleMap[(int) $user->row_id]['name'] ?? null,
                    'is_village_user' => (bool) $user->is_village_user,
                    'photo_url' => $user->photo_path
                        ? asset('storage/'.ltrim((string) $user->photo_path, '/')).'?v='.($user->updated_at?->timestamp ?? time())
                        : null,
                    'last_login_at' => $user->last_login_at?->toDateTimeString(),
                    'can_login' => $isActive && $hasActiveMembership,
                    'is_self' => (int) $user->row_id === (int) $request->user()?->row_id,
                ];
            })->values()->all(),
        ]);
    }

    public function login(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->tenantContext->tenant();
        $this->ensureEnabled();

        // Never let this endpoint escape the tenant resolved from the host.
        abort_unless((int) $user->tenant_id === (int) $tenant->row_id, 404);
        abort_if((bool) $user->is_superadmin, 404, 'Akun pusat tidak dapat login dari halaman ini.');
        abort_if((bool) $user->is_regency_user || (bool) $user->is_province_user, 404);
        abort_unless($user->status === 'active', 403, 'Akun ini sedang tidak aktif.');

        $hasActiveMembership = TenantMembership::query()
            ->where('tenant_id', $tenant->row_id)
            ->where('user_id', $user->row_id)
            ->where('status', 'active')
            ->exists();

        abort_unless($hasActiveMembership, 403, 'Akun ini tidak terhubung aktif ke tenant ini.');

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

        return redirect()
            ->route('dashboard')
            ->with('success', "Login otomatis sebagai {$user->name} ({$tenant->name}).");
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('quick_login.enabled', false), 404);
    }
}
