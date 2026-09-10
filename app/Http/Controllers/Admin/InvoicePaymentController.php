<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ManualPaymentRequest;
use App\Models\Platform\Invoice;
use App\Services\Billing\InvoicePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvoicePaymentController
{
    public function storeManual(ManualPaymentRequest $request, Invoice $invoice, InvoicePaymentService $payments): RedirectResponse
    {
        $payments->recordManual($invoice, $request->validated(), $request->user());

        return back()->with('success', 'Pembayaran manual dicatat.');
    }

    public function storeTripay(Request $request, Invoice $invoice, InvoicePaymentService $payments): RedirectResponse
    {
        $payment = $payments->initiateTripay($invoice, $request->user());
        $url = $payment->tripay_checkout_url;

        return back()->with('success', $url
            ? "Link Tripay dibuat: {$url}"
            : 'Transaksi Tripay dibuat.');
    }
}
