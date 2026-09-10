<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Invoice;
use App\Models\Platform\InvoicePayment;
use App\Services\Billing\DuitkuClient;
use App\Services\Billing\InvoicePaymentService;
use App\Services\Billing\TripayClient;
use App\Services\Billing\XenditClient;
use App\Services\PlatformSettingService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class InvoiceController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly TripayClient $tripayClient,
        private readonly DuitkuClient $duitkuClient,
        private readonly XenditClient $xenditClient,
        private readonly PlatformSettingService $settings,
    ) {}

    public function index(Request $request, TenantContext $context): Response
    {
        $this->permissions->denyUnless($request->user(), 'billing.view');

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;
        $sort = in_array($request->query('sort'), ['number', 'due_at', 'amount', 'status', 'issued_at'], true)
            ? (string) $request->query('sort')
            : 'row_id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $invoices = Invoice::query()
            ->where('tenant_id', $context->id())
            ->where('status', '!=', 'draft')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => [
                'row_id' => $invoice->row_id,
                'number' => $invoice->number,
                'purpose' => $invoice->purpose,
                'status' => $invoice->status,
                'blocks_access' => (bool) $invoice->blocks_access,
                'is_blocking' => $invoice->isBlockingAccess(),
                'amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'remaining' => $invoice->remainingAmount(),
                'currency' => $invoice->currency,
                'due_at' => $invoice->due_at?->toDateString(),
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'description' => $invoice->description,
            ]);

        return Inertia::render('Billing/Invoices/Index', compact('invoices', 'search', 'status', 'perPage', 'sort', 'direction'));
    }

    public function show(Request $request, Invoice $invoice, TenantContext $context): Response
    {
        $this->permissions->denyUnless($request->user(), 'billing.view');
        $this->assertTenantOwns($invoice, $context);

        $invoice->load([
            'subscription.plan:row_id,code,name',
            'payments' => fn ($q) => $q->latest('row_id'),
        ]);

        $activeGateway = (string) ($this->settings->get('billing.active_gateway') ?: 'duitku');

        if ($activeGateway === 'xendit' && ($this->xenditClient->getSecretKey() !== '' || config('xendit.secret_key') !== '')) {
            $channels = array_map(static fn ($c) => array_merge($c, ['gateway' => 'xendit']), $this->xenditClient->getPaymentChannels());
        } elseif ($activeGateway === 'duitku' && ($this->duitkuClient->getMerchantCode() !== '' || config('duitku.merchant_code') !== '')) {
            $channels = array_map(static fn ($c) => array_merge($c, ['gateway' => 'duitku']), $this->duitkuClient->getPaymentChannels());
        } else {
            $channels = array_map(static fn ($c) => array_merge($c, ['gateway' => 'tripay']), $this->tripayClient->getPaymentChannels());
        }

        // Extract active in-app payment details (pending Tripay, Duitku, or Xendit payment)
        $activePayment = $invoice->payments->first(
            fn (InvoicePayment $p) => in_array($p->method, ['tripay', 'duitku', 'xendit'], true) && $p->status === 'pending',
        );

        $activePaymentDetails = null;
        if ($activePayment !== null) {
            $payload = $activePayment->tripay_payload ?? [];
            $activePaymentDetails = [
                'payment_id' => $activePayment->row_id,
                'gateway' => $activePayment->method,
                'reference' => $activePayment->reference ?: $activePayment->tripay_reference,
                'method_code' => $payload['paymentMethod'] ?? $payload['payment_method'] ?? 'ONLINE',
                'payment_name' => $payload['payment_name'] ?? ($activePayment->method === 'xendit' ? 'Pembayaran Xendit' : ($activePayment->method === 'duitku' ? 'Pembayaran Duitku' : 'Online Payment')),
                'pay_code' => $payload['vaNumber'] ?? $payload['pay_code'] ?? null,
                'qr_url' => $payload['qrCode'] ?? $payload['qr_url'] ?? null,
                'qr_string' => $payload['qrCode'] ?? $payload['qr_string'] ?? null,
                'amount' => (int) ($payload['amount'] ?? $activePayment->amount),
                'total_amount' => (int) ($payload['amount'] ?? $activePayment->amount),
                'fee_customer' => (int) ($payload['fee_customer'] ?? 0),
                'expired_time' => isset($payload['expired_time']) ? date('Y-m-d H:i:s', (int) $payload['expired_time']) : null,
                'instructions' => $payload['instructions'] ?? [],
                'checkout_url' => $activePayment->tripay_checkout_url ?: ($payload['invoice_url'] ?? $payload['paymentUrl'] ?? null),
            ];
        }

        return Inertia::render('Billing/Invoices/Show', [
            'invoice' => [
                'row_id' => $invoice->row_id,
                'public_id' => $invoice->public_id,
                'number' => $invoice->number,
                'purpose' => $invoice->purpose,
                'status' => $invoice->status,
                'blocks_access' => (bool) $invoice->blocks_access,
                'is_blocking' => $invoice->isBlockingAccess(),
                'amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'remaining' => $invoice->remainingAmount(),
                'currency' => $invoice->currency,
                'description' => $invoice->description,
                'notes' => $invoice->notes,
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'due_at' => $invoice->due_at?->toDateString(),
                'paid_at' => $invoice->paid_at?->toDateTimeString(),
                'subscription' => $invoice->subscription ? [
                    'row_id' => $invoice->subscription->row_id,
                    'status' => $invoice->subscription->status,
                    'plan' => $invoice->subscription->plan?->only(['code', 'name']),
                ] : null,
                'is_open' => $invoice->isOpen() && $invoice->status !== 'draft',
            ],
            'active_gateway' => $activeGateway,
            'channels' => $channels,
            'active_payment' => $activePaymentDetails,
            'payments' => $invoice->payments->map(fn ($p) => [
                'row_id' => $p->row_id,
                'method' => $p->method,
                'status' => $p->status,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at?->toDateTimeString(),
                'reference' => $p->reference,
                'tripay_reference' => $p->tripay_reference,
                'tripay_checkout_url' => $p->tripay_checkout_url,
                'payment_name' => $p->tripay_payload['payment_name'] ?? ($p->method === 'xendit' ? 'Xendit Payment' : ($p->method === 'duitku' ? 'Duitku Payment' : null)),
                'pay_code' => $p->tripay_payload['vaNumber'] ?? $p->tripay_payload['pay_code'] ?? null,
                'qr_url' => $p->tripay_payload['qrCode'] ?? $p->tripay_payload['qr_url'] ?? null,
                'notes' => $p->notes,
            ]),
        ]);
    }

    public function pay(Request $request, Invoice $invoice, TenantContext $context, InvoicePaymentService $payments): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'billing.pay');
        $this->assertTenantOwns($invoice, $context);

        $gateway = (string) $request->input('gateway', '');
        if ($gateway === '') {
            $configuredGateway = (string) $this->settings->get('billing.active_gateway', '');
            if ($configuredGateway !== '') {
                $gateway = $configuredGateway;
            } elseif ($this->tripayClient->getApiKey() !== '' || config('tripay.api_key') !== '') {
                $gateway = 'tripay';
            } elseif ($this->duitkuClient->getMerchantCode() !== '' || config('duitku.merchant_code') !== '') {
                $gateway = 'duitku';
            } elseif ($this->xenditClient->getSecretKey() !== '' || config('xendit.secret_key') !== '') {
                $gateway = 'xendit';
            } else {
                $gateway = 'tripay';
            }
        }

        $paymentMethod = (string) $request->input('payment_method', match ($gateway) {
            'xendit' => $this->xenditClient->getDefaultMethod(),
            'duitku' => $this->duitkuClient->getDefaultMethod(),
            default => $this->tripayClient->getDefaultMethod(),
        });

        try {
            if ($gateway === 'xendit') {
                $payment = $payments->initiateXendit($invoice, $request->user(), $paymentMethod);
            } elseif ($gateway === 'duitku') {
                $payment = $payments->initiateDuitku($invoice, $request->user(), $paymentMethod);
            } else {
                $payment = $payments->initiateTripay($invoice, $request->user(), $paymentMethod);
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kode / Link pembayaran berhasil dibuat. Silakan selesaikan pembayaran.');
    }

    public function checkStatus(Request $request, Invoice $invoice, TenantContext $context, InvoicePaymentService $payments): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'billing.pay');
        $this->assertTenantOwns($invoice, $context);

        $activePayment = $invoice->payments()->whereIn('method', ['tripay', 'duitku', 'xendit'])->where('status', 'pending')->latest('row_id')->first();
        if ($activePayment !== null) {
            $updated = $payments->checkAndSyncStatus($activePayment);
            if ($updated->status === 'paid') {
                return back()->with('success', 'Pembayaran berhasil dikonfirmasi! Langganan Anda telah aktif.');
            }
        }

        return back()->with('info', 'Status pembayaran belum berubah. Silakan lakukan pembayaran jika belum.');
    }

    private function assertTenantOwns(Invoice $invoice, TenantContext $context): void
    {
        if ((int) $invoice->tenant_id !== $context->id() || $invoice->status === 'draft') {
            abort(404);
        }
    }
}
