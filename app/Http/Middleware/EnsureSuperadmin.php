<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if ($user->is_superadmin !== true) {
            // Tenant users: back to app. Superadmin-only accounts never hit this branch.
            $fallback = $user->tenant_id !== null ? route('dashboard') : route('login');

            return redirect()
                ->to($fallback)
                ->with('error', 'Halaman admin hanya untuk superadmin. Login: superadmin / password.');
        }

        return $next($request);
    }
}
