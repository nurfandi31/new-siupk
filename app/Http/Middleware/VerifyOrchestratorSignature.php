<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies inbound tool calls from the assistant orchestrator.
 *
 * Headers (preferred):
 *   X-Orchestrator-Signature  = HMAC-SHA256(ts + '.' + rawBody, key_hash)
 *   X-Orchestrator-Timestamp  = ms epoch string
 *   X-Orchestrator-Key-Hash   = sha256(shared_secret)  [optional]
 *
 * key_hash = sha256(ASSISTANT_SHARED_SECRET).
 *
 * Aliases accepted for backward compatibility: X-Assistant-*.
 */
final class VerifyOrchestratorSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = (string) config('assistant.shared_secret', '');
        if ($plaintext === '') {
            return response()->json(['error' => 'assistant not configured'], 503);
        }

        $expectedHash = hash('sha256', $plaintext);
        $sig = $this->header($request, 'X-Orchestrator-Signature', 'X-Assistant-Signature');
        $ts = $this->header($request, 'X-Orchestrator-Timestamp', 'X-Assistant-Timestamp');
        $keyHash = $this->header($request, 'X-Orchestrator-Key-Hash', 'X-Assistant-Key-Hash');

        if ($sig === '' || $ts === '' || ! ctype_digit($ts)) {
            return response()->json(['error' => 'missing signature headers'], 401);
        }

        if ($keyHash !== '' && ! hash_equals($expectedHash, $keyHash)) {
            return response()->json(['error' => 'key hash mismatch'], 401);
        }

        $skew = (int) config('assistant.signature_max_skew_ms', 300_000);
        $now = (int) floor(microtime(true) * 1000);
        if (abs($now - (int) $ts) > $skew) {
            return response()->json(['error' => 'signature timestamp skew'], 401);
        }

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $ts.'.'.$raw, $expectedHash);

        if (! hash_equals($expected, $sig)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        return $next($request);
    }

    private function header(Request $request, string $primary, string $fallback): string
    {
        $value = (string) $request->header($primary, '');
        if ($value !== '') {
            return $value;
        }

        return (string) $request->header($fallback, '');
    }
}
