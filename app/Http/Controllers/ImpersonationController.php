<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TenantImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ImpersonationController
{
    public function consume(Request $request, string $token, TenantImpersonationService $service): RedirectResponse
    {
        try {
            $result = $service->consumeToken($token);
            $user = $result['user'];
            $tenant = $result['tenant'];
            $impersonator = $result['impersonator'];

            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }

            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            $guard = Auth::guard('web');
            $passwordHash = $user->fresh()?->getAuthPassword();
            if ($passwordHash !== null && method_exists($guard, 'hashPasswordForCookie')) {
                $passwordHash = $guard->hashPasswordForCookie($passwordHash);
                $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $passwordHash);
            }

            $request->session()->put('impersonated_by', $impersonator->row_id);
            $request->session()->put('impersonator_name', $impersonator->name);
            $request->session()->put('impersonated_tenant_id', $tenant->row_id);
            $request->session()->put('impersonated_at', now()->toIso8601String());

            $user->forceFill(['last_login_at' => now()])->saveQuietly();

            return redirect()->route('dashboard')->with('success', "Auto-login berhasil. Anda sedang mengakses tenant [{$tenant->name}] sebagai [{$user->name}].");
        } catch (\Throwable $e) {
            return redirect()->route('login')->with('error', $e->getMessage() ?: 'Gagal melakukan auto-login.');
        }
    }

    public function leave(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonated_by');
        $request->session()->forget(['impersonated_by', 'impersonator_name', 'impersonated_tenant_id', 'impersonated_at']);

        if ($impersonatorId !== null) {
            $superadmin = User::query()->where('row_id', $impersonatorId)->where('is_superadmin', true)->first();
            if ($superadmin !== null) {
                Auth::guard('web')->login($superadmin);
                $request->session()->regenerate();

                return redirect()->route('admin.tenants.index')->with('success', 'Sesi impersonasi diakhiri. Kembali ke panel Superadmin.');
            }
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Sesi impersonasi diakhiri.');
    }
}
