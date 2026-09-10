<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Services\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    public function __construct(
        private readonly PermissionChecker $permissions,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $this->permissions->denyUnless($request->user(), $permission);

        return $next($request);
    }
}
