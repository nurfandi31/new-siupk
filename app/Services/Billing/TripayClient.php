<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\PlatformSettingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TripayClient
{
    public function __construct(
        private readonly ?PlatformSettingService $settings = null,
    ) {}

    public function getMerchantCode(): string
    {
        return (string) ($this->settings?->get('tripay.merchant_code') ?: config('tripay.merchant_code', ''));
    }

    public function getApiKey(): string
    {
        return (string) ($this->settings?->getEncrypted('tripay.api_key') ?: config('tripay.api_key', ''));
    }

    public function getPrivateKey(): string
    {
        return (string) ($this->settings?->getEncrypted('tripay.private_key') ?: config('tripay.private_key', ''));
    }

    public function getMode(): string
    {
        return (string) ($this->settings?->get('tripay.mode') ?: config('tripay.mode', 'sandbox'));
    }

    public function getDefaultMethod(): string
    {
        return (string) ($this->settings?->get('tripay.default_method') ?: config('tripay.default_method', 'QRIS2'));
    }

    public function createTransaction(
        int $amount,
        string $merchantRef,
        string $customerName,
        ?string $customerEmail,
        array $orderItems,
        ?string $paymentMethod = null,
        ?string $returnUrl = null,
    ): array {
        $merchantCode = $this->getMerchantCode();
        $privateKey = $this->getPrivateKey();
        $apiKey = $this->getApiKey();
        $method = $paymentMethod ?: $this->getDefaultMethod();

        if ($merchantCode === '' || $privateKey === '' || $apiKey === '') {
            throw new RuntimeException('Konfigurasi Tripay belum lengkap.');
        }

        $signature = hash_hmac('sha256', $merchantCode.$merchantRef.$amount, $privateKey);

        $payload = [
            'method' => $method,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail ?: 'billing@'.parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
            'order_items' => $orderItems,
            'return_url' => $returnUrl ?: (string) config('app.url', 'http://localhost'),
            'signature' => $signature,
        ];

        $callbackUrl = (string) config('tripay.callback_url', '');
        if ($callbackUrl !== '') {
            $payload['callback_url'] = $callbackUrl;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post('/transaction/create', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('Gagal membuat transaksi Tripay: '.$exception->getMessage(), previous: $exception);
        }

        if (! ($response['success'] ?? false)) {
            throw new RuntimeException('Tripay menolak transaksi: '.($response['message'] ?? 'unknown'));
        }

        return $response['data'] ?? [];
    }

    /**
     * @return list<array{code: string, name: string, group: string, type: string, fee_flat: int, fee_percent: float, icon_url: ?string, is_active: bool}>
     */
    public function getPaymentChannels(): array
    {
        $apiKey = $this->getApiKey();

        if ($apiKey !== '') {
            try {
                $response = Http::baseUrl($this->baseUrl())
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->get('/merchant/payment-channel')
                    ->json();

                if (($response['success'] ?? false) && is_array($response['data'] ?? null)) {
                    $channels = [];
                    foreach ($response['data'] as $c) {
                        $group = match ($c['group'] ?? '') {
                            'Virtual Account' => 'virtual_account',
                            'QRIS' => 'qris',
                            'Convenience Store' => 'retail',
                            'E-Wallet' => 'ewallet',
                            default => 'other',
                        };

                        $channels[] = [
                            'code' => (string) $c['code'],
                            'name' => (string) $c['name'],
                            'group' => $group,
                            'type' => (string) ($c['type'] ?? 'direct'),
                            'fee_flat' => (int) ($c['fee_customer']['flat'] ?? 0),
                            'fee_percent' => (float) ($c['fee_customer']['percent'] ?? 0),
                            'icon_url' => $c['icon_url'] ?? null,
                            'is_active' => (bool) ($c['active'] ?? true),
                        ];
                    }

                    if ($channels !== []) {
                        return $channels;
                    }
                }
            } catch (\Throwable) {
                // Fallback to default channels list
            }
        }

        return $this->defaultChannels();
    }

    public function checkTransactionStatus(string $reference): ?array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === '' || $reference === '') {
            return null;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($apiKey)
                ->acceptJson()
                ->get('/transaction/detail', ['reference' => $reference])
                ->json();

            if (($response['success'] ?? false) && is_array($response['data'] ?? null)) {
                return $response['data'];
            }
        } catch (\Throwable) {
            // Ignore failure on detail poll
        }

        return null;
    }

    public function verifyCallbackSignature(string $callbackSignature, string $jsonBody): bool
    {
        $privateKey = $this->getPrivateKey();
        if ($privateKey === '' || $callbackSignature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $jsonBody, $privateKey);

        return hash_equals($expected, $callbackSignature);
    }

    /**
     * @return list<array{code: string, name: string, group: string, type: string, fee_flat: int, fee_percent: float, icon_url: ?string, is_active: bool}>
     */
    public function defaultChannels(): array
    {
        return [
            // QRIS
            [
                'code' => 'QRIS2',
                'name' => 'QRIS (Semua Bank & E-Wallet)',
                'group' => 'qris',
                'type' => 'direct',
                'fee_flat' => 750,
                'fee_percent' => 0.7,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/qris.png',
                'is_active' => true,
            ],
            // Virtual Accounts
            [
                'code' => 'BCAVA',
                'name' => 'BCA Virtual Account',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/bcava.png',
                'is_active' => true,
            ],
            [
                'code' => 'BRIVA',
                'name' => 'BRI Virtual Account (BRIVA)',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/briva.png',
                'is_active' => true,
            ],
            [
                'code' => 'BNIVA',
                'name' => 'BNI Virtual Account',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/bniva.png',
                'is_active' => true,
            ],
            [
                'code' => 'MANDIRIVA',
                'name' => 'Mandiri Virtual Account',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/mandiriva.png',
                'is_active' => true,
            ],
            [
                'code' => 'PERMATAVA',
                'name' => 'Permata Virtual Account',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/permatava.png',
                'is_active' => true,
            ],
            [
                'code' => 'CIMBVA',
                'name' => 'CIMB Niaga Virtual Account',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/cimbva.png',
                'is_active' => true,
            ],
            [
                'code' => 'BSIVA',
                'name' => 'BSI Virtual Account',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/bsiva.png',
                'is_active' => true,
            ],
            [
                'code' => 'DANAMONVA',
                'name' => 'Danamon Virtual Account',
                'group' => 'virtual_account',
                'type' => 'direct',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://tripay.co.id/images/payment-channel/danamonva.png',
                'is_active' => true,
            ],
        ];
    }

    private function baseUrl(): string
    {
        return $this->getMode() === 'production'
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';
    }
}
