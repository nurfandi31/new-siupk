<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\PlatformSettingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DuitkuClient
{
    public function __construct(
        private readonly ?PlatformSettingService $settings = null,
    ) {}

    public function getMerchantCode(): string
    {
        return (string) ($this->settings?->get('duitku.merchant_code') ?: config('duitku.merchant_code', ''));
    }

    public function getApiKey(): string
    {
        return (string) ($this->settings?->getEncrypted('duitku.api_key') ?: config('duitku.api_key', ''));
    }

    public function getMode(): string
    {
        return (string) ($this->settings?->get('duitku.mode') ?: config('duitku.mode', 'sandbox'));
    }

    public function getDefaultMethod(): string
    {
        return (string) ($this->settings?->get('duitku.default_method') ?: config('duitku.default_method', 'VC'));
    }

    /**
     * Create inquiry transaction with Duitku API v2.
     *
     * @param  string  $merchantRef  (merchantOrderId)
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
        $merchantCode = $this->getMerchantCode();
        $apiKey = $this->getApiKey();
        $method = $paymentMethod ?: $this->getDefaultMethod();

        if ($merchantCode === '' || $apiKey === '') {
            throw new RuntimeException('Konfigurasi Duitku belum lengkap (Merchant Code / API Key belum diisi).');
        }

        // Signature for Inquiry V2: MD5(merchantcode + merchantOrderId + paymentAmount + apiKey)
        $signature = md5($merchantCode.$merchantRef.$amount.$apiKey);

        $productDetail = ! empty($orderItems) && isset($orderItems[0]['name'])
            ? (string) $orderItems[0]['name']
            : 'Pembayaran Invoice '.$merchantRef;

        $email = $customerEmail ?: 'billing@'.parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST);
        $cbUrl = (string) (config('duitku.callback_url') ?: route('duitku.callback'));
        $retUrl = $returnUrl ?: (string) (config('duitku.return_url') ?: config('app.url', 'http://localhost'));

        $payload = [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $amount,
            'paymentMethod' => $method,
            'merchantOrderId' => $merchantRef,
            'productDetails' => $productDetail,
            'email' => $email,
            'customerVaName' => $customerName,
            'callbackUrl' => $cbUrl,
            'returnUrl' => $retUrl,
            'signature' => $signature,
            'expiryPeriod' => (int) config('duitku.expiry_period', 1440),
        ];

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->post('/v2/inquiry', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('Gagal membuat transaksi Duitku: '.$exception->getMessage(), previous: $exception);
        }

        if (($response['statusCode'] ?? '') !== '00') {
            throw new RuntimeException('Duitku menolak transaksi: '.($response['statusMessage'] ?? 'Unknown Error'));
        }

        return [
            'merchant_ref' => $merchantRef,
            'reference' => $response['reference'] ?? $merchantRef,
            'payment_url' => $response['paymentUrl'] ?? null,
            'checkout_url' => $response['paymentUrl'] ?? null,
            'pay_code' => $response['vaNumber'] ?? $response['qrCode'] ?? null,
            'qr_string' => $response['qrCode'] ?? null,
            'amount' => $amount,
            'statusCode' => $response['statusCode'] ?? '00',
            'statusMessage' => $response['statusMessage'] ?? 'SUCCESS',
            'raw' => $response,
        ];
    }

    /**
     * Get active payment methods from Duitku.
     *
     * @return list<array{code: string, name: string, group: string, fee_flat: int, fee_percent: float, icon_url: ?string, is_active: bool}>
     */
    public function getPaymentChannels(int $amount = 10000): array
    {
        $merchantCode = $this->getMerchantCode();
        $apiKey = $this->getApiKey();

        if ($merchantCode !== '' && $apiKey !== '') {
            $datetime = date('Y-m-d H:i:s');
            // Signature for getpaymentmethod: SHA256(merchantcode + amount + datetime + apiKey)
            $signature = hash('sha256', $merchantCode.$amount.$datetime.$apiKey);

            try {
                $response = Http::baseUrl($this->baseUrl())
                    ->acceptJson()
                    ->asJson()
                    ->post('/paymentmethod/getpaymentmethod', [
                        'merchantcode' => $merchantCode,
                        'amount' => $amount,
                        'datetime' => $datetime,
                        'signature' => $signature,
                    ])
                    ->json();

                if (is_array($response) && isset($response['paymentFee']) && is_array($response['paymentFee'])) {
                    $channels = [];
                    foreach ($response['paymentFee'] as $item) {
                        $code = (string) ($item['paymentMethod'] ?? '');
                        $name = (string) ($item['paymentName'] ?? '');
                        $fee = (int) ($item['totalFee'] ?? 0);
                        $iconUrl = $item['paymentImage'] ?? null;

                        $group = match (true) {
                            str_contains(strtolower($name), 'qris') || str_contains(strtolower($code), 'qr') => 'qris',
                            str_contains(strtolower($name), 'va') || str_contains(strtolower($name), 'virtual account') => 'virtual_account',
                            str_contains(strtolower($name), 'ewallet') || str_contains(strtolower($name), 'ovo') || str_contains(strtolower($name), 'shopee') || str_contains(strtolower($name), 'dana') => 'ewallet',
                            str_contains(strtolower($name), 'retail') || str_contains(strtolower($name), 'indomaret') || str_contains(strtolower($name), 'alfamart') => 'retail',
                            default => 'other',
                        };

                        $channels[] = [
                            'code' => $code,
                            'name' => $name,
                            'group' => $group,
                            'fee_flat' => $fee,
                            'fee_percent' => 0.0,
                            'icon_url' => $iconUrl,
                            'is_active' => true,
                        ];
                    }

                    if (! empty($channels)) {
                        return $channels;
                    }
                }
            } catch (\Throwable) {
                // Fallback to default channels list
            }
        }

        return $this->defaultChannels();
    }

    /**
     * Check transaction status directly with Duitku API.
     */
    public function checkTransactionStatus(string $merchantOrderId): ?array
    {
        $merchantCode = $this->getMerchantCode();
        $apiKey = $this->getApiKey();

        if ($merchantCode === '' || $apiKey === '' || $merchantOrderId === '') {
            return null;
        }

        // Signature: MD5(merchantCode + merchantOrderId + apiKey)
        $signature = md5($merchantCode.$merchantOrderId.$apiKey);

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->post('/transactionStatus', [
                    'merchantCode' => $merchantCode,
                    'merchantOrderId' => $merchantOrderId,
                    'signature' => $signature,
                ])
                ->json();

            if (is_array($response) && isset($response['statusCode'])) {
                return $response;
            }
        } catch (\Throwable) {
            // Ignore error on status inquiry poll
        }

        return null;
    }

    /**
     * Verify Duitku Webhook/Callback signature.
     * Formula: MD5(merchantCode + amount + merchantOrderId + apiKey)
     */
    public function verifyCallbackSignature(string $callbackSignature, string $merchantOrderId, string|int $amount): bool
    {
        $merchantCode = $this->getMerchantCode();
        $apiKey = $this->getApiKey();

        if ($merchantCode === '' || $apiKey === '' || $callbackSignature === '') {
            return false;
        }

        $expected = md5($merchantCode.$amount.$merchantOrderId.$apiKey);

        return hash_equals(strtolower($expected), strtolower($callbackSignature));
    }

    /**
     * Default list of supported Duitku channels.
     */
    public function defaultChannels(): array
    {
        return [
            [
                'code' => 'SP',
                'name' => 'ShopeePay / QRIS Duitku',
                'group' => 'qris',
                'fee_flat' => 750,
                'fee_percent' => 0.7,
                'icon_url' => 'https://images.duitku.com/payment/sp.png',
                'is_active' => true,
            ],
            [
                'code' => 'BC',
                'name' => 'BCA Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 4000,
                'fee_percent' => 0,
                'icon_url' => 'https://images.duitku.com/payment/bc.png',
                'is_active' => true,
            ],
            [
                'code' => 'BR',
                'name' => 'BRI Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://images.duitku.com/payment/br.png',
                'is_active' => true,
            ],
            [
                'code' => 'BN',
                'name' => 'BNI Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://images.duitku.com/payment/bn.png',
                'is_active' => true,
            ],
            [
                'code' => 'M2',
                'name' => 'Mandiri Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://images.duitku.com/payment/m2.png',
                'is_active' => true,
            ],
            [
                'code' => 'VA',
                'name' => 'Permata Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://images.duitku.com/payment/va.png',
                'is_active' => true,
            ],
            [
                'code' => 'B1',
                'name' => 'CIMB Niaga Virtual Account',
                'group' => 'virtual_account',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://images.duitku.com/payment/b1.png',
                'is_active' => true,
            ],
            [
                'code' => 'BT',
                'name' => 'Permata Bank VA',
                'group' => 'virtual_account',
                'fee_flat' => 3500,
                'fee_percent' => 0,
                'icon_url' => 'https://images.duitku.com/payment/bt.png',
                'is_active' => true,
            ],
        ];
    }

    private function baseUrl(): string
    {
        return $this->getMode() === 'production'
            ? 'https://passport.duitku.com/webapi/api/merchant'
            : 'https://sandbox.duitku.com/webapi/api/merchant';
    }
}
