<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\PhoneNormalizer;
use App\Services\PlatformWhatsappGatewayService;
use App\Services\WhatsappGatewayService;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class WhatsAppPasswordOtpService
{
    private const OTP_TTL_MINUTES = 10;

    private const OTP_MAX_PER_PHONE_PER_HOUR = 3;

    public function __construct(
        private TenantContext $context,
        private TenantResolver $tenantResolver,
        private ShardConnectionManager $shardConnections,
        private WhatsappGatewayService $gateway,
        private PlatformWhatsappGatewayService $platformGateway,
    ) {}

    /**
     * @return array{success:bool,phone:?string}
     */
    public function issueOtp(User $user): array
    {
        $phone = app(PhoneNormalizer::class)->normalize((string) $user->phone);

        if ($phone === '' || ! $this->hasValidIndonesianFormat($phone)) {
            return ['success' => false, 'phone' => null];
        }

        $recentCount = DB::connection($this->platformConnection())->table('password_reset_tokens')
            ->where('phone', $phone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= self::OTP_MAX_PER_PHONE_PER_HOUR) {
            return ['success' => false, 'phone' => $phone];
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->issueToken($user, $phone, $otp);

        if (! $this->sendWhatsApp($phone, $otp, $user->tenant_id === null ? null : (int) $user->tenant_id)) {
            DB::connection($this->platformConnection())->table('password_reset_tokens')
                ->where('phone', $phone)
                ->delete();

            return ['success' => false, 'phone' => $phone];
        }

        return ['success' => true, 'phone' => $phone];
    }

    public function consumeOtp(User $user, string $phone, string $otp): bool
    {
        $record = DB::connection($this->platformConnection())->table('password_reset_tokens')
            ->where('phone', $phone)
            ->where('user_row_id', $user->row_id)
            ->lockForUpdate()
            ->first();

        if ($record === null || $this->isExpired($record->created_at)) {
            DB::connection($this->platformConnection())->table('password_reset_tokens')
                ->where('phone', $phone)
                ->delete();

            return false;
        }

        if ((int) $record->attempts >= 5) {
            DB::connection($this->platformConnection())->table('password_reset_tokens')
                ->where('phone', $phone)
                ->delete();

            return false;
        }

        if (! Hash::check($otp, (string) $record->token)) {
            DB::connection($this->platformConnection())->table('password_reset_tokens')
                ->where('phone', $phone)
                ->where('user_row_id', $user->row_id)
                ->increment('attempts');

            return false;
        }

        DB::connection($this->platformConnection())->table('password_reset_tokens')
            ->where('phone', $phone)
            ->where('user_row_id', $user->row_id)
            ->delete();

        return true;
    }

    private function issueToken(User $user, string $phone, string $otp): string
    {
        // Housekeeping: drop stale rows (> 1 hour, older than the hourly
        // rate-limit window) so the table does not grow unbounded.
        DB::connection($this->platformConnection())->table('password_reset_tokens')
            ->where('created_at', '<', now()->subHour())
            ->delete();

        DB::connection($this->platformConnection())->table('password_reset_tokens')->insert([
            'phone' => $phone,
            'user_row_id' => $user->row_id,
            'token' => Hash::make($otp),
            'attempts' => 0,
            'created_at' => now(),
        ]);

        return $phone;
    }

    private function sendWhatsApp(string $phone, string $otp, ?int $tenantId): bool
    {
        $message = "Kode reset password SIUPK Anda adalah {$otp}. Berlaku 10 menit. Jangan bagikan kode ini.";

        $platformResult = $this->platformGateway->sendText($phone, $message);
        if ($platformResult['success']) {
            Log::info('WhatsApp password OTP sent', [
                'phone_masked' => $this->maskPhone($phone),
                'source' => 'platform',
            ]);

            return true;
        }

        Log::warning('WhatsApp password OTP platform send failed', [
            'phone_masked' => $this->maskPhone($phone),
            'source' => 'platform',
            'reason' => $platformResult['message'],
        ]);

        if ($tenantId === null) {
            Log::warning('WhatsApp password OTP not sent', [
                'phone_masked' => $this->maskPhone($phone),
                'source' => 'platform',
                'reason' => 'Superadmin hanya dapat menggunakan instance WhatsApp platform.',
            ]);

            return false;
        }

        return (bool) $this->runWithTenantContext($tenantId, function () use ($phone, $message): bool {
            $result = $this->gateway->sendText($phone, $message);

            if (! $result['success']) {
                Log::warning('WhatsApp password OTP not sent', [
                    'phone_masked' => $this->maskPhone($phone),
                    'source' => 'tenant',
                    'reason' => $result['message'],
                ]);
            } else {
                Log::info('WhatsApp password OTP sent', [
                    'phone_masked' => $this->maskPhone($phone),
                    'source' => 'tenant',
                ]);
            }

            return (bool) $result['success'];
        });
    }

    private function runWithTenantContext(int $tenantId, callable $callback): mixed
    {
        $wasInitialized = $this->context->isInitialized();

        if ($wasInitialized) {
            return $callback();
        }

        $tenant = $this->tenantResolver->resolveById($tenantId);
        $this->context->initialize($tenant, $tenant->placement, $tenant->placement->shard);

        try {
            $this->shardConnections->connect($tenant->placement->shard);

            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            return false;
        } finally {
            $this->shardConnections->disconnect();
            $this->context->clear();
        }
    }

    private function isExpired(?string $createdAt): bool
    {
        return $createdAt === null || now()->diffInMinutes($createdAt) > self::OTP_TTL_MINUTES;
    }

    private function hasValidIndonesianFormat(string $phone): bool
    {
        return preg_match('/^628\d{7,12}$/', $phone) === 1;
    }

    private function platformConnection(): string
    {
        return (string) config('tenancy.platform_connection', 'platform');
    }

    public function maskPhone(string $phone): string
    {
        if (strlen($phone) < 5) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 2).str_repeat('*', strlen($phone) - 4).substr($phone, -2);
    }
}
