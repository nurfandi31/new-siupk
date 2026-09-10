<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\PlatformSettingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class XenditClient
{
    public function __construct(
        private readonly ?PlatformSettingService $settings = null,
    ) {}

    public function getSecretKey(): string
    {
        return (string) ($this->settings?->getEncrypted('xendit.secret_key') ?: config('xendit.secret_key', ''));
    }

    public function getPublicKey(): string
    {
        return (string) ($this->settings?->get('xendit.public_key') ?: config('xendit.public_key', ''));
    }

    public function getCallbackToken(): string
    {
        return (string) ($this->settings?->getEncrypted('xendit.callback_token') ?: config('xendit.callback_token', ''));
    }

    public function getMode(): string
    {
        return (string) ($this->settings?->get('xendit.mode') ?: config('xendit.mode', 'sandbox'));
    }

    public function getDefaultMethod(): string
    {
        return (string) ($this->settings?->get('xendit.default_method') ?: config('xendit.default_method', 'QRIS'));
    }

    /**
     * Create an Invoice with Xendit Invoices API v2.
     *
     * @param  string  $merchantRef  (external_id)
     */
    public function createTransaction(
        int $amount,
        string $merchantRef,
        string $customerName,
        ?string $customerEmail = null,
        array $orderItems = [],
        ?string $paymentMethod = null,
        ?string $returnUrl = null,
    ): array {
        $secretKey = $this->getSecretKey();

        if ($secretKey === '') {
            throw new RuntimeException('Konfigurasi Xendit belum lengkap (Secret Key belum diisi).');
        }

        $description = ! empty($orderItems) && isset($orderItems[0]['name'])
            ? (string) $orderItems[0]['name']
            : 'Pembayaran Invoice '.$merchantRef;

        $email = $customerEmail ?: 'billing@'.parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST);
        $redirectUrl = $returnUrl ?: (string) config('app.url', 'http://localhost');

        $payload = [
            'external_id' => $merchantRef,
            'amount' => $amount,
            'description' => $description,
            'invoice_duration' => (int) config('xendit.expiry_period', 86400),
            'customer' => [
                'given_names' => $customerName,
                'email' => $email,
            ],
            'currency' => 'IDR',
            'success_redirect_url' => $redirectUrl,
            'failure_redirect_url' => $redirectUrl,
        ];

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withBasicAuth($secretKey, '')
                ->acceptJson()
                ->asJson()
                ->post('/v2/invoices', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('Gagal membuat transaksi Xendit: '.$exception->getMessage(), previous: $exception);
        }

        return [
            'merchant_ref' => $merchantRef,
            'reference' => $response['id'] ?? $merchantRef,
            'payment_url' => $response['invoice_url'] ?? null,
            'checkout_url' => $response['invoice_url'] ?? null,
            'pay_code' => $response['id'] ?? null,
            'qr_string' => null,
            'amount' => (int) ($response['amount'] ?? $amount),
            'status' => $response['status'] ?? 'PENDING',
            'expiry_date' => $response['expiry_date'] ?? null,
            'raw' => $response,
        ];
    }

    /**
     * Get active payment channels supported by Xendit.
     *
     * @return list<array{code: string, name: string, group: string, fee_flat: int, fee_percent: float, icon_url: ?string, is_active: bool}>
     */
    public function getPaymentChannels(): array
    {
        $secretKey = $this->getSecretKey();

        if ($secretKey !== '') {
            try {
                $response = Http::baseUrl($this->baseUrl())
                    ->withBasicAuth($secretKey, '')
                    ->acceptJson()
                    ->get('/v2/invoices', ['limit' => 1])
                    ->json();

                if (is_array($response)) {
                    return $this->defaultChannels();
                }
            } catch (\Throwable) {
                // Fallback to default channels list
            }
        }

        return $this->defaultChannels();
    }

    /**
     * Check transaction status directly with Xendit API.
     */
    public function checkTransactionStatus(string $xenditInvoiceId): ?array
    {
        $secretKey = $this->getSecretKey();

        if ($secretKey === '' || $xenditInvoiceId === '') {
            return null;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withBasicAuth($secretKey, '')
                ->acceptJson()
                ->get("/v2/invoices/{$xenditInvoiceId}")
                ->json();

            if (is_array($response) && isset($response['status'])) {
                return $response;
            }
        } catch (\Throwable) {
            // Ignore error on status inquiry poll
        }

        return null;
    }

    /**
     * Verify Xendit Webhook/Callback verification token header.
     */
    public function verifyCallbackToken(string $xenditCallbackToken): bool
    {
        $configuredToken = $this->getCallbackToken();
        if ($configuredToken === '') {
            $configuredToken = $this->getSecretKey();
        }

        if ($configuredToken === '' || $xenditCallbackToken === '') {
            return false;
        }

        return hash_equals($configuredToken, $xenditCallbackToken);
    }

    /**
     * Default list of supported Xendit payment channels.
     */
    public function defaultChannels(): array
    {
        return [
            [
                'code' => 'QRIS',
                'name' => 'QRIS Xendit (Semua Bank & E-Wallet)',
                'group' => 'qris',
                'fee_flat' => 750,
                'fee_percent' => 0.7,
                'icon_url' => 'https://xendit.co/wp-content/uploads/2020/09/qris.png',
                'is_active' => true,
            ],
            [
                'code' => 'BCA',
                'name' => 'BCA Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 4500,
                'fee_percent' => 0,
                'icon_url' => 'https://xendit.co/wp-content/uploads/2020/09/bca.png',
                'is_active' => true,
            ],
            [
                'code' => 'BRI',
                'name' => 'BRI Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'icon_url' => 'https://xendit.co/wp-content/uploads/2020/09/bri.png',
                'is_active' => true,
            ],
            [
                'code' => 'BNI',
                'name' => 'BNI Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'icon_url' => 'https://xendit.co/wp-content/uploads/2020/09/bni.png',
                'is_active' => true,
            ],
            [
                'code' => 'MANDIRI',
                'name' => 'Mandiri Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'icon_url' => 'https://xendit.co/wp-content/uploads/2020/09/mandiri.png',
                'is_active' => true,
            ],
            [
                'code' => 'PERMATA',
                'name' => 'Permata Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'icon_url' => 'https://xendit.co/wp-content/uploads/2020/09/permata.png',
                'is_active' => true,
            ],
            [
                'code' => 'CREDIT_CARD',
                'name' => 'Kartu Kredit / Debit (Visa/Mastercard)',
                'group' => 'other',
                'fee_flat' => 2000,
                'fee_percent' => 2.9,
                'icon_url' => 'https://xendit.co/wp-content/uploads/2020/09/credit-card.png',
                'is_active' => true,
            ],
        ];
    }

    private function baseUrl(): string
    {
        return 'https://api.xendit.co';
    }
}
