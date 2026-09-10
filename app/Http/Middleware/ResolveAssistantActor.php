<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the orchestrator tool call to a real SIUPK user.
 *
 * Body shape from tool-executor:
 *   { tool, external_user_id, params, ts }
 *
 * external_user_id = platform users.row_id (string).
 */
final class ResolveAssistantActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $externalId = $request->input('external_user_id');
        // Fallback: some clients only put fields in raw JSON body.
        if (($externalId === null || $externalId === '') && str_contains((string) $request->header('Content-Type'), 'json')) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded) && array_key_exists('external_user_id', $decoded)) {
                $externalId = $decoded['external_user_id'];
                // Merge so controllers also see params/tool.
                $request->merge([
                    'external_user_id' => $decoded['external_user_id'] ?? null,
                    'tool' => $decoded['tool'] ?? $request->input('tool'),
                    'params' => $decoded['params'] ?? $request->input('params', []),
                ]);
            }
        }

        if ($externalId === null || $externalId === '' || ! ctype_digit((string) $externalId)) {
            return response()->json(['error' => 'external_user_id required'], 400);
        }

        $userId = (int) $externalId;
        $user = User::query()
            ->where('row_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            return response()->json(['error' => 'unknown external_user_id'], 403);
        }

        // Tenant middleware already resolved host/membership; ensure actor belongs here.
        $tenantId = $user->tenant_id;
        $ctxTenant = app(TenantContext::class)->id();
        if ($tenantId !== null && (int) $tenantId !== $ctxTenant) {
            return response()->json(['error' => 'user not in this tenant'], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('assistant_actor', $user);

        return $next($request);
    }
}
