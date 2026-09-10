<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Services\Billing\InvoicePaymentService;
use App\Services\Billing\XenditClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class XenditWebhookController
{
    public function __invoke(Request $request, XenditClient $xendit, InvoicePaymentService $payments): Response
    {
        $tokenHeader = (string) $request->header('x-callback-token', $request->header('X-Callback-Token', ''));

        if (! $xendit->verifyCallbackToken($tokenHeader)) {
            Log::warning('xendit.callback.invalid_token', [
                'received_token' => $tokenHeader,
            ]);

            return response('Invalid token', 403);
        }

        try {
            $payments->handleXenditCallback($request->all());
        } catch (\Throwable $exception) {
            Log::error('xendit.callback.failed', ['message' => $exception->getMessage()]);

            return response('Failed', 400);
        }

        return response('OK', 200);
    }
}
