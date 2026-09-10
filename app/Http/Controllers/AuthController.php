<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController
{
    public function showLogin(): Response|RedirectResponse
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

        return Inertia::render('Auth/Login');
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

        if ($user->tenant_id === null && ! $user->is_superadmin && ! $user->isRegencyUser()) {
            $membership = $user->memberships()->where('status', 'active')->first();
            $user->forceFill(['tenant_id' => $membership?->tenant_id])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user, $remember);
        Auth::logoutOtherDevices($password);
        $request->session()->regenerate();

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

        return redirect()->route('login');
    }
}
