<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Services\MenuAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks direct page visits to tenant menus that the user's role is not
 * allowed to open. Only page loads (GET/HEAD) are guarded; mutations remain
 * protected by their own permission/FormRequest checks.
 */
final class EnsureMenuAccess
{
    public function __construct(
        private readonly MenuAccess $menus,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if (! $this->menus->allowsPath($request->user(), $request->path())) {
            abort(403, 'Menu ini tidak diizinkan untuk role Anda.');
        }

        return $next($request);
    }
}
