<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyDesktopApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('services.desktop.enabled', true) === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Desktop synchronization API is currently disabled.',
            ], 403);
        }

        $configuredKey = (string) config('services.desktop.api_key', '');

        // If no API key is set in configuration:
        // - In production: reject all (401) with a clear message that DESKTOP_SYNC_API_KEY is required.
        // - In non-production: allow requests (dev/testing fallback or authenticated user).
        if ($configuredKey === '') {
            if (app()->isProduction()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. DESKTOP_SYNC_API_KEY must be configured in production.',
                ], 401);
            }

            return $next($request);
        }

        $providedKey = $this->extractToken($request);

        if ($providedKey !== '' && hash_equals($configuredKey, $providedKey)) {
            return $next($request);
        }

        // Allow authenticated platform superadmin or tenant users
        $user = $request->user();
        if ($user !== null) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized. Invalid or missing Desktop Sync API token.',
        ], 401);
    }

    private function extractToken(Request $request): string
    {
        $bearer = (string) $request->bearerToken();
        if ($bearer !== '') {
            return trim($bearer);
        }

        $headerKey = (string) ($request->header('X-Desktop-Key') ?? $request->header('X-API-Key') ?? '');
        if ($headerKey !== '') {
            return trim($headerKey);
        }

        return '';
    }
}
