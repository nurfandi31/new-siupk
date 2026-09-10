<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Services\Billing\InvoicePaymentService;
use App\Services\Billing\TripayClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class TripayWebhookController
{
    public function __invoke(Request $request, TripayClient $tripay, InvoicePaymentService $payments): Response
    {
        $signature = (string) $request->header('X-Callback-Signature', '');
        $body = $request->getContent();

        if (! $tripay->verifyCallbackSignature($signature, $body)) {
            Log::warning('tripay.callback.invalid_signature');

            return response('Invalid signature', 403);
        }

        try {
            $payments->handleTripayCallback($request->all());
        } catch (\Throwable $exception) {
            Log::error('tripay.callback.failed', ['message' => $exception->getMessage()]);

            return response('Failed', 400);
        }

        return response('OK', 200);
    }
}
