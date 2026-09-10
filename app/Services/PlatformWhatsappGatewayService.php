<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\WhatsappPlatformInstance;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PlatformWhatsappGatewayService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    /**
     * @return array{success:bool,instance:string,qr:?string,pairingCode:?string,state:string,message:string}
     */
    public function createSession(string $name): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'instance' => $name,
                'qr' => null,
                'pairingCode' => null,
                'state' => 'unconfigured',
                'message' => 'WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum dikonfigurasi.',
            ];
        }

        try {
            $response = $this->request('post', '/create-instance', [
                'instance' => $name,
                'lokasi' => 'platform',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Platform WhatsApp createSession failed', [
                'instance' => $name,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'instance' => $name,
                'qr' => null,
                'pairingCode' => null,
                'state' => 'network_error',
                'message' => 'Gagal menghubungi server gateway: '.$exception->getMessage(),
            ];
        }

        $payload = $this->payload($response);
        $qr = $payload['qr'] ?? $payload['instance']['qr'] ?? null;
        $state = $payload['state'] ?? $payload['instance']['status'] ?? ($response->successful() ? 'connecting' : 'failed');
        $this->updateStatus($name, (string) $state);

        return [
            'success' => $response->successful() && (bool) ($payload['success'] ?? true),
            'instance' => $name,
            'qr' => is_string($qr) && $qr !== '' ? $qr : null,
            'pairingCode' => $payload['pairingCode'] ?? null,
            'state' => (string) $state,
            'message' => (string) ($payload['message'] ?? ($response->successful() ? 'Instance berhasil dibuat.' : 'Gagal membuat instance.')),
        ];
    }

    /**
     * @return array{success:bool,status:string,state:?string,qr:?string,instance:string,message:string,payload?:array<string,mixed>}
     */
    public function connectionState(string $name): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'unconfigured',
                'state' => null,
                'qr' => null,
                'instance' => $name,
                'message' => 'WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum diisi.',
            ];
        }

        try {
            $response = $this->request('get', '/instance-state', query: ['instance' => $name]);
        } catch (\Throwable $exception) {
            Log::warning('Platform WhatsApp connectionState failed', [
                'instance' => $name,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'network_error',
                'state' => null,
                'qr' => null,
                'instance' => $name,
                'message' => 'Gagal menghubungi server: '.$exception->getMessage(),
            ];
        }

        $payload = $this->payload($response);
        $state = $this->extractState($payload);
        $qr = $payload['qr'] ?? $payload['instance']['qr'] ?? null;

        if ($response->status() === 404) {
            $this->updateStatus($name, 'disconnected');

            return [
                'success' => true,
                'status' => 'missing',
                'state' => 'missing',
                'qr' => null,
                'instance' => $name,
                'message' => 'Instance belum dibuat. Klik "Buat QR" untuk memulainya.',
                'payload' => $payload,
            ];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => 'http_'.$response->status(),
                'state' => $state,
                'qr' => is_string($qr) ? $qr : null,
                'instance' => $name,
                'message' => 'Server gateway mengembalikan status '.$response->status().'.',
                'payload' => $payload,
            ];
        }

        $connected = in_array(strtolower((string) $state), ['open', 'connected'], true);
        $normalizedState = $connected ? 'open' : ($state ?: 'connecting');
        $this->updateStatus($name, $normalizedState);

        return [
            'success' => true,
            'status' => $connected ? 'open' : 'connecting',
            'state' => $normalizedState,
            'qr' => $connected ? null : (is_string($qr) ? $qr : null),
            'instance' => $name,
            'message' => $connected ? 'WhatsApp terhubung.' : 'WhatsApp belum terhubung (scan QR).',
            'payload' => $payload,
        ];
    }

    /**
     * @return array{success:bool,deleted:bool,message:string}
     */
    public function deleteSession(string $name): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'deleted' => false, 'message' => 'Gateway belum dikonfigurasi.'];
        }

        try {
            $response = $this->request('delete', '/delete-instance', query: ['instance' => $name]);
            $payload = $this->payload($response);
            $this->updateStatus($name, 'disconnected');

            return [
                'success' => $response->successful(),
                'deleted' => $response->successful(),
                'message' => (string) ($payload['message'] ?? ($response->successful() ? 'Session WhatsApp berhasil dihapus.' : 'Gagal menghapus session.')),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Platform WhatsApp deleteSession failed', [
                'instance' => $name,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'deleted' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @return array{success:bool,message:string,data?:mixed,instance?:string}
     */
    public function sendText(string $phone, string $message, ?string $instance = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum diisi di environment.'];
        }

        $normalized = $this->phoneNormalizer->normalize($phone);
        if ($normalized === '') {
            return ['success' => false, 'message' => 'Nomor penerima kosong/tidak valid.'];
        }

        $message = trim($message);
        if ($message === '') {
            return ['success' => false, 'message' => 'Isi pesan kosong.'];
        }

        $targetInstance = $instance ?: $this->resolveOtpInstance();
        if ($targetInstance === null) {
            return ['success' => false, 'message' => 'Tidak ada instance WhatsApp platform aktif yang terhubung.'];
        }

        try {
            $response = $this->request('post', '/send-message', [
                'instance' => $targetInstance,
                'number' => $normalized,
                'text' => $message,
            ]);
            $payload = $this->payload($response);
        } catch (\Throwable $exception) {
            Log::warning('Platform WhatsApp sendText exception', [
                'instance' => $targetInstance,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'instance' => $targetInstance, 'message' => 'Exception: '.$exception->getMessage()];
        }

        if ($response->successful() && ($payload['success'] ?? true)) {
            return ['success' => true, 'message' => 'Pesan terkirim.', 'data' => $payload, 'instance' => $targetInstance];
        }

        Log::warning('Platform WhatsApp sendText failed', [
            'instance' => $targetInstance,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'success' => false,
            'instance' => $targetInstance,
            'message' => (string) ($payload['message'] ?? 'Gagal mengirim pesan: HTTP '.$response->status()),
        ];
    }

    public function resolveOtpInstance(): ?string
    {
        $connected = WhatsappPlatformInstance::query()
            ->where('is_active', true)
            ->whereIn('status', ['open', 'connected'])
            ->orderByDesc('is_default')
            ->orderBy('row_id')
            ->first();

        if ($connected !== null) {
            return (string) $connected->instance_name;
        }

        $active = WhatsappPlatformInstance::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('row_id')
            ->first();

        return $active?->instance_name;
    }

    private function updateStatus(string $name, string $status): void
    {
        try {
            WhatsappPlatformInstance::query()
                ->where('instance_name', $name)
                ->update(['status' => $status, 'updated_at' => now()]);
        } catch (\Throwable) {
            // Ignore non-critical status updates.
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $pending = $this->http
            ->withToken(base64_encode($this->apiKey()), 'Basic')
            ->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            ])
            ->timeout($this->timeout())
            ->acceptJson();

        if ($query !== []) {
            $pending = $pending->withQueryParameters($query);
        }

        return match (strtolower($method)) {
            'get' => $pending->get($url),
            'post' => $pending->post($url, $body ?? []),
            'delete' => $pending->delete($url, $body ?? []),
            default => throw new RuntimeException('Unsupported HTTP method: '.$method),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function extractState(array $payload): ?string
    {
        $candidates = [
            $payload['instance']['status'] ?? null,
            $payload['instance']['state'] ?? null,
            $payload['state'] ?? null,
            $payload['status'] ?? null,
            $payload['connectionStatus'] ?? null,
        ];

        $state = collect($candidates)->first(fn ($candidate) => is_string($candidate) && $candidate !== '');

        return is_string($state) ? $state : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.wa_gateway.base_url', ''), '/');
    }

    private function apiKey(): string
    {
        return (string) config('services.wa_gateway.api_key', '');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.wa_gateway.timeout', 15));
    }
}
