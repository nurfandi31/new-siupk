<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\DuitkuClient;
use App\Services\Billing\TripayClient;
use App\Services\Billing\XenditClient;
use App\Services\PlatformSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentGatewayController extends Controller
{
    public function index(PlatformSettingService $settings): Response
    {
        return Inertia::render('Admin/PaymentGateways/Index', [
            'active_gateway' => (string) ($settings->get('billing.active_gateway') ?: 'duitku'),
            'xendit' => [
                'secret_key' => (string) ($settings->getEncrypted('xendit.secret_key') ?? config('xendit.secret_key', '')),
                'has_secret_key' => ! empty($settings->getEncrypted('xendit.secret_key') ?? config('xendit.secret_key')),
                'public_key' => (string) ($settings->get('xendit.public_key') ?? config('xendit.public_key', '')),
                'mode' => (string) ($settings->get('xendit.mode') ?? config('xendit.mode', 'sandbox')),
                'default_method' => (string) ($settings->get('xendit.default_method') ?? config('xendit.default_method', 'QRIS')),
            ],
            'duitku' => [
                'merchant_code' => (string) ($settings->get('duitku.merchant_code') ?? config('duitku.merchant_code', '')),
                'has_api_key' => ! empty($settings->getEncrypted('duitku.api_key') ?? config('duitku.api_key')),
                'mode' => (string) ($settings->get('duitku.mode') ?? config('duitku.mode', 'sandbox')),
                'default_method' => (string) ($settings->get('duitku.default_method') ?? config('duitku.default_method', 'VC')),
            ],
            'tripay' => [
                'merchant_code' => (string) ($settings->get('tripay.merchant_code') ?? config('tripay.merchant_code', '')),
                'has_api_key' => ! empty($settings->getEncrypted('tripay.api_key') ?? config('tripay.api_key')),
                'has_private_key' => ! empty($settings->getEncrypted('tripay.private_key') ?? config('tripay.private_key')),
                'mode' => (string) ($settings->get('tripay.mode') ?? config('tripay.mode', 'sandbox')),
                'default_method' => (string) ($settings->get('tripay.default_method') ?? config('tripay.default_method', 'QRIS2')),
            ],
        ]);
    }

    public function updateActiveGateway(Request $request, PlatformSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', 'string', 'in:tripay,duitku,xendit'],
        ]);

        $settings->set('billing.active_gateway', $validated['gateway']);
        $settings->flush();

        return redirect()->back()->with('success', sprintf('Payment Gateway utama berhasil diubah menjadi %s.', strtoupper($validated['gateway'])));
    }

    public function updateTripay(Request $request, PlatformSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_code' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'private_key' => ['nullable', 'string', 'max:500'],
            'mode' => ['required', 'string', 'in:sandbox,production'],
            'default_method' => ['required', 'string', 'max:50'],
        ]);

        $settings->set('tripay.merchant_code', trim($validated['merchant_code']));
        $settings->set('tripay.mode', $validated['mode']);
        $settings->set('tripay.default_method', $validated['default_method']);

        if (! empty($validated['api_key'])) {
            $settings->setEncrypted('tripay.api_key', trim($validated['api_key']));
        }

        if (! empty($validated['private_key'])) {
            $settings->setEncrypted('tripay.private_key', trim($validated['private_key']));
        }

        $settings->flush();

        return redirect()->back()->with('success', 'Kredensial & konfigurasi Tripay Payment Gateway berhasil disimpan.');
    }

    public function testTripay(TripayClient $tripay): JsonResponse
    {
        try {
            $channels = $tripay->getPaymentChannels();

            return response()->json([
                'ok' => true,
                'message' => sprintf('Koneksi ke Tripay API (%s) BERHASIL! Menemukan %d saluran pembayaran.', config('tripay.mode'), count($channels)),
                'channels' => $channels,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal terhubung ke Tripay: '.$e->getMessage(),
            ], 422);
        }
    }

    public function updateDuitku(Request $request, PlatformSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_code' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'mode' => ['required', 'string', 'in:sandbox,production'],
            'default_method' => ['required', 'string', 'max:50'],
        ]);

        $settings->set('duitku.merchant_code', trim($validated['merchant_code']));
        $settings->set('duitku.mode', $validated['mode']);
        $settings->set('duitku.default_method', $validated['default_method']);

        if (! empty($validated['api_key'])) {
            $settings->setEncrypted('duitku.api_key', trim($validated['api_key']));
        }

        $settings->flush();

        return redirect()->back()->with('success', 'Kredensial & konfigurasi Duitku Payment Gateway berhasil disimpan.');
    }

    public function testDuitku(DuitkuClient $duitku): JsonResponse
    {
        try {
            $channels = $duitku->getPaymentChannels();

            return response()->json([
                'ok' => true,
                'message' => sprintf('Koneksi ke Duitku API (%s) BERHASIL! Menemukan %d saluran pembayaran.', $duitku->getMode(), count($channels)),
                'channels' => $channels,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal terhubung ke Duitku: '.$e->getMessage(),
            ], 422);
        }
    }

    public function updateXendit(Request $request, PlatformSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'secret_key' => ['nullable', 'string', 'max:500'],
            'public_key' => ['nullable', 'string', 'max:500'],
            'callback_token' => ['nullable', 'string', 'max:500'],
            'mode' => ['required', 'string', 'in:sandbox,production'],
            'default_method' => ['required', 'string', 'max:50'],
        ]);

        $settings->set('xendit.mode', $validated['mode']);
        $settings->set('xendit.default_method', $validated['default_method']);

        if (! empty($validated['public_key'])) {
            $settings->set('xendit.public_key', trim($validated['public_key']));
        }

        if (! empty($validated['secret_key'])) {
            $settings->setEncrypted('xendit.secret_key', trim($validated['secret_key']));
        }

        if (! empty($validated['callback_token'])) {
            $settings->setEncrypted('xendit.callback_token', trim($validated['callback_token']));
        }

        $settings->flush();

        return redirect()->back()->with('success', 'Kredensial & konfigurasi Xendit Payment Gateway berhasil disimpan.');
    }

    public function testXendit(XenditClient $xendit): JsonResponse
    {
        try {
            $channels = $xendit->getPaymentChannels();

            return response()->json([
                'ok' => true,
                'message' => sprintf('Koneksi ke Xendit API (%s) BERHASIL! Menemukan %d saluran pembayaran.', $xendit->getMode(), count($channels)),
                'channels' => $channels,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal terhubung ke Xendit: '.$e->getMessage(),
            ], 422);
        }
    }
}
