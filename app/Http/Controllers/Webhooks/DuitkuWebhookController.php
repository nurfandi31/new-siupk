<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Services\Billing\DuitkuClient;
use App\Services\Billing\InvoicePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class DuitkuWebhookController
{
    public function __invoke(Request $request, DuitkuClient $duitku, InvoicePaymentService $payments): Response
    {
        $signature = (string) $request->input('signature', '');
        $merchantOrderId = (string) $request->input('merchantOrderId', '');
        $amount = (string) $request->input('amount', '');

        if (! $duitku->verifyCallbackSignature($signature, $merchantOrderId, $amount)) {
            Log::warning('duitku.callback.invalid_signature', [
                'merchant_order_id' => $merchantOrderId,
                'signature' => $signature,
            ]);

            return response('Invalid signature', 403);
        }

        try {
            $payments->handleDuitkuCallback($request->all());
        } catch (\Throwable $exception) {
            Log::error('duitku.callback.failed', ['message' => $exception->getMessage()]);

            return response('Failed', 400);
        }

        return response('OK', 200);
    }
}
