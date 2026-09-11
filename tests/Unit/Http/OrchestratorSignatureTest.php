<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Middleware\VerifyOrchestratorSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * HMAC: key_hash = sha256(shared_secret); sig = HMAC-SHA256(ts + '.' + body, key_hash)
 */
final class OrchestratorSignatureTest extends TestCase
{
    public function test_valid_signature_passes_middleware(): void
    {
        $plaintext = 'tk_test_secret_key_for_unit';
        Config::set('assistant.shared_secret', $plaintext);
        Config::set('assistant.signature_max_skew_ms', 300_000);

        $body = json_encode([
            'tool' => 'search_members',
            'external_user_id' => '1',
            'params' => ['query' => 'budi'],
            'ts' => 1,
        ], JSON_THROW_ON_ERROR);

        $ts = (string) (int) floor(microtime(true) * 1000);
        $keyHash = hash('sha256', $plaintext);
        $sig = hash_hmac('sha256', $ts.'.'.$body, $keyHash);

        $request = Request::create('/api/assistant/tools/search_members', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ORCHESTRATOR_SIGNATURE' => $sig,
            'HTTP_X_ORCHESTRATOR_TIMESTAMP' => $ts,
            'HTTP_X_ORCHESTRATOR_KEY_HASH' => $keyHash,
        ], $body);

        $passed = false;
        $response = (new VerifyOrchestratorSignature)->handle(
            $request,
            function () use (&$passed): Response {
                $passed = true;

                return response('ok');
            },
        );

        self::assertTrue($passed);
        self::assertSame(200, $response->getStatusCode());
    }

    public function test_assistant_header_alias_accepted(): void
    {
        $plaintext = 'tk_test_secret_key_for_unit';
        Config::set('assistant.shared_secret', $plaintext);
        Config::set('assistant.signature_max_skew_ms', 300_000);

        $body = '{"tool":"search_groups","external_user_id":"1","params":{"query":"mawar"}}';
        $ts = (string) (int) floor(microtime(true) * 1000);
        $keyHash = hash('sha256', $plaintext);
        $sig = hash_hmac('sha256', $ts.'.'.$body, $keyHash);

        $request = Request::create('/api/assistant/tools', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ASSISTANT_SIGNATURE' => $sig,
            'HTTP_X_ASSISTANT_TIMESTAMP' => $ts,
        ], $body);

        $passed = false;
        $response = (new VerifyOrchestratorSignature)->handle(
            $request,
            function () use (&$passed): Response {
                $passed = true;

                return response('ok');
            },
        );

        self::assertTrue($passed);
        self::assertSame(200, $response->getStatusCode());
    }

    public function test_bad_signature_rejected(): void
    {
        Config::set('assistant.shared_secret', 'tk_test_secret_key_for_unit');
        Config::set('assistant.signature_max_skew_ms', 300_000);

        $body = '{"tool":"search_members","external_user_id":"1","params":{}}';
        $ts = (string) (int) floor(microtime(true) * 1000);

        $request = Request::create('/api/assistant/tools', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ORCHESTRATOR_SIGNATURE' => str_repeat('a', 64),
            'HTTP_X_ORCHESTRATOR_TIMESTAMP' => $ts,
        ], $body);

        $response = (new VerifyOrchestratorSignature)->handle(
            $request,
            fn (): Response => response('should-not-run'),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('invalid signature', $response->getData(true)['error'] ?? null);
    }

    public function test_missing_config_returns_503(): void
    {
        Config::set('assistant.shared_secret', '');

        $request = Request::create('/api/assistant/tools', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        $response = (new VerifyOrchestratorSignature)->handle(
            $request,
            fn (): Response => response('should-not-run'),
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('assistant not configured', $response->getData(true)['error'] ?? null);
    }

    public function test_key_hash_mismatch_rejected(): void
    {
        $plaintext = 'tk_test_secret_key_for_unit';
        Config::set('assistant.shared_secret', $plaintext);
        Config::set('assistant.signature_max_skew_ms', 300_000);

        $body = '{}';
        $ts = (string) (int) floor(microtime(true) * 1000);
        $keyHash = hash('sha256', $plaintext);
        $sig = hash_hmac('sha256', $ts.'.'.$body, $keyHash);

        $request = Request::create('/api/assistant/tools', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ORCHESTRATOR_SIGNATURE' => $sig,
            'HTTP_X_ORCHESTRATOR_TIMESTAMP' => $ts,
            'HTTP_X_ORCHESTRATOR_KEY_HASH' => str_repeat('0', 64),
        ], $body);

        $response = (new VerifyOrchestratorSignature)->handle(
            $request,
            fn (): Response => response('should-not-run'),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('key hash mismatch', $response->getData(true)['error'] ?? null);
    }
}
