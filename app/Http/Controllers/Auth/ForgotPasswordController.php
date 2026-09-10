<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Auth\WhatsAppPasswordOtpService;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class ForgotPasswordController
{
    private const SESSION_KEY = 'password_reset';

    private const MAX_RESENDS = 3;

    public function __construct(
        private readonly WhatsAppPasswordOtpService $otpService,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function showRequestForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
        ]);

        $identifier = trim((string) $validated['identifier']);
        $normalizedPhone = $this->phoneNormalizer->normalize($identifier);
        $isPhone = preg_match('/^628\d{7,12}$/', $normalizedPhone) === 1;

        $user = User::query()
            ->where('status', 'active')
            ->whereNotNull('tenant_id')
            ->where(function ($query) use ($identifier, $normalizedPhone, $isPhone): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);

                if ($isPhone) {
                    $query->orWhere('phone', $normalizedPhone);
                }
            })
            ->first();

        $issuedPhone = null;

        if ($user !== null) {
            $result = $this->otpService->issueOtp($user);

            if ($result['success']) {
                $issuedPhone = (string) $result['phone'];
            } else {
                Log::warning('Password reset OTP not issued', [
                    'phone_masked' => $result['phone'] !== null
                        ? $this->otpService->maskPhone((string) $result['phone'])
                        : null,
                    'reason' => 'rate_limited_or_gateway_failure',
                ]);
            }
        }

        if ($issuedPhone !== null) {
            $sessionData = [
                'issued_at' => now()->toIso8601String(),
                'resends' => 0,
                'phone' => $issuedPhone,
                'user_id' => $user->row_id,
            ];

            $request->session()->put(self::SESSION_KEY, $sessionData);
        } else {
            $request->session()->forget(self::SESSION_KEY);
        }

        return redirect()
            ->route('password.otp.form')
            ->with('info', 'Jika akun Anda aktif dan memiliki nomor WhatsApp yang valid, kode OTP sudah dikirim melalui WhatsApp. Periksa pesan Anda.');
    }

    public function showOtpForm(Request $request): Response|RedirectResponse
    {
        $sessionData = $request->session()->get(self::SESSION_KEY);

        if (! is_array($sessionData) || ! isset($sessionData['phone'])) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/VerifyOtp', [
            'maskedPhone' => $this->otpService->maskPhone((string) $sessionData['phone']),
            'resendsLeft' => max(0, self::MAX_RESENDS - (int) ($sessionData['resends'] ?? 0)),
        ]);
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $sessionData = $request->session()->get(self::SESSION_KEY);

        if (! is_array($sessionData) || ! isset($sessionData['phone'], $sessionData['user_id'])) {
            return redirect()->route('password.request');
        }

        if ((int) ($sessionData['resends'] ?? 0) >= self::MAX_RESENDS) {
            return back()->withErrors(['otp' => 'Batas kirim ulang tercapai. Silakan minta kode baru dari halaman awal.']);
        }

        $user = User::query()->find((int) $sessionData['user_id']);

        if ($user === null || (string) $user->phone !== (string) $sessionData['phone']) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('password.request');
        }

        $result = $this->otpService->issueOtp($user);

        if ($result['success']) {
            $sessionData['resends'] = (int) ($sessionData['resends'] ?? 0) + 1;
            $request->session()->put(self::SESSION_KEY, $sessionData);

            return back()->with('info', 'Kode OTP baru telah dikirim melalui WhatsApp.');
        }

        Log::warning('Password reset OTP resend failed', [
            'phone_masked' => $this->otpService->maskPhone((string) $sessionData['phone']),
        ]);

        return back()->withErrors(['otp' => 'Kode OTP gagal dikirim. Coba lagi beberapa saat lagi.']);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $sessionData = $request->session()->get(self::SESSION_KEY);

        if (! is_array($sessionData) || ! isset($sessionData['phone'], $sessionData['user_id'])) {
            return redirect()
                ->route('password.request')
                ->withErrors(['otp' => 'Sesi reset tidak ditemukan. Silakan minta kode OTP baru.']);
        }

        $user = User::query()->find((int) $sessionData['user_id']);

        if ($user === null || (string) $user->phone !== (string) $sessionData['phone']) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()
                ->route('password.request')
                ->withErrors(['otp' => 'Sesi reset tidak valid. Silakan minta kode OTP baru.']);
        }

        if (! $this->otpService->consumeOtp($user, (string) $sessionData['phone'], (string) $validated['otp'])) {
            return back()->withErrors(['otp' => 'Kode OTP salah, kedaluwarsa, atau sudah digunakan.']);
        }

        $request->session()->put(self::SESSION_KEY, array_merge($sessionData, [
            'grant_token' => Str::random(64),
        ]));

        return redirect()->route('password.reset.form');
    }

    public function showResetForm(Request $request): Response|RedirectResponse
    {
        $sessionData = $request->session()->get(self::SESSION_KEY);

        if (! is_array($sessionData) || ! isset($sessionData['grant_token'])) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/ResetPassword', [
            'grantToken' => $sessionData['grant_token'],
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'grant_token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $sessionData = $request->session()->get(self::SESSION_KEY);

        if (
            ! is_array($sessionData)
            || ! isset($sessionData['grant_token'], $sessionData['user_id'])
            || ! hash_equals((string) $sessionData['grant_token'], (string) $validated['grant_token'])
        ) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()
                ->route('password.request')
                ->withErrors(['otp' => 'Sesi reset tidak valid. Silakan ulangi proses lupa password.']);
        }

        $user = User::query()->find((int) $sessionData['user_id']);

        if ($user === null) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()
                ->route('password.request')
                ->withErrors(['otp' => 'Akun tidak ditemukan. Silakan ulangi proses lupa password.']);
        }

        $user->forceFill(['password' => $validated['password']])->save();
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::logout();

        return redirect()
            ->route('login')
            ->with('success', 'Password berhasil direset. Silakan login dengan password baru Anda.');
    }
}
