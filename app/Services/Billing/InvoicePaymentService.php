<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Platform\Invoice;
use App\Models\Platform\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class InvoicePaymentService
{
    public function __construct(
        private InvoiceService $invoices,
        private TripayClient $tripay,
        private ?DuitkuClient $duitku = null,
        private ?XenditClient $xendit = null,
    ) {}

    public function recordManual(Invoice $invoice, array $data, User $actor): InvoicePayment
    {
        if (! $invoice->isOpen() || $invoice->status === 'draft') {
            throw new RuntimeException('Invoice tidak menerima pembayaran.');
        }

        $amount = (string) $data['amount'];
        if (bccomp($amount, '0', 2) !== 1) {
            throw new RuntimeException('Nominal pembayaran harus lebih dari 0.');
        }

        $remaining = $invoice->remainingAmount();
        if (bccomp($amount, $remaining, 2) === 1) {
            throw new RuntimeException('Nominal melebihi sisa tagihan.');
        }

        return DB::connection('platform')->transaction(function () use ($invoice, $data, $actor, $amount): InvoicePayment {
            $payment = InvoicePayment::query()->create([
                'public_id' => (string) Str::ulid(),
                'invoice_id' => $invoice->row_id,
                'tenant_id' => $invoice->tenant_id,
                'method' => 'manual',
                'status' => 'paid',
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now(),
                'reference' => $data['reference'] ?? null,
                'recorded_by' => $actor->row_id,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->forceFill([
                'amount_paid' => bcadd((string) $invoice->amount_paid, $amount, 2),
            ])->save();

            $this->invoices->refreshStatus($invoice->fresh());

            return $payment;
        });
    }

    public function initiateTripay(Invoice $invoice, ?User $actor = null, ?string $paymentMethod = null): InvoicePayment
    {
        if (! $invoice->isOpen() || $invoice->status === 'draft') {
            throw new RuntimeException('Invoice tidak menerima pembayaran.');
        }

        $remaining = $invoice->remainingAmount();
        if (bccomp($remaining, '0', 2) !== 1) {
            throw new RuntimeException('Invoice sudah lunas.');
        }

        $invoice->loadMissing('tenant');
        $merchantRef = (string) Str::ulid();
        $selectedMethod = $paymentMethod ?: (string) config('tripay.default_method', 'QRIS2');

        $payment = InvoicePayment::query()->create([
            'public_id' => (string) Str::ulid(),
            'invoice_id' => $invoice->row_id,
            'tenant_id' => $invoice->tenant_id,
            'method' => 'tripay',
            'status' => 'pending',
            'amount' => $remaining,
            'reference' => $merchantRef,
            'recorded_by' => $actor?->row_id,
        ]);

        $customerName = $invoice->tenant?->name ?: 'Tenant';
        $result = $this->tripay->createTransaction(
            amount: (int) round((float) $remaining),
            merchantRef: $merchantRef,
            customerName: $customerName,
            customerEmail: null,
            orderItems: [[
                'name' => $invoice->description ?: $invoice->number,
                'price' => (int) round((float) $remaining),
                'quantity' => 1,
            ]],
            paymentMethod: $selectedMethod,
        );

        $payment->forceFill([
            'tripay_reference' => $result['reference'] ?? null,
            'tripay_checkout_url' => $result['checkout_url'] ?? null,
            'tripay_payload' => $result,
        ])->save();

        return $payment->fresh();
    }

    public function initiateDuitku(Invoice $invoice, ?User $actor = null, ?string $paymentMethod = null): InvoicePayment
    {
        if (! $invoice->isOpen() || $invoice->status === 'draft') {
            throw new RuntimeException('Invoice tidak menerima pembayaran.');
        }

        $remaining = $invoice->remainingAmount();
        if (bccomp($remaining, '0', 2) !== 1) {
            throw new RuntimeException('Invoice sudah lunas.');
        }

        $invoice->loadMissing('tenant');
        $merchantRef = (string) Str::ulid();
        $client = $this->duitku ?? app(DuitkuClient::class);
        $selectedMethod = $paymentMethod ?: $client->getDefaultMethod();

        $payment = InvoicePayment::query()->create([
            'public_id' => (string) Str::ulid(),
            'invoice_id' => $invoice->row_id,
            'tenant_id' => $invoice->tenant_id,
            'method' => 'duitku',
            'status' => 'pending',
            'amount' => $remaining,
            'reference' => $merchantRef,
            'recorded_by' => $actor?->row_id,
        ]);

        $customerName = $invoice->tenant?->name ?: 'Tenant';
        $result = $client->createTransaction(
            amount: (int) round((float) $remaining),
            merchantRef: $merchantRef,
            customerName: $customerName,
            customerEmail: null,
            orderItems: [[
                'name' => $invoice->description ?: $invoice->number,
                'price' => (int) round((float) $remaining),
                'quantity' => 1,
            ]],
            paymentMethod: $selectedMethod,
        );

        $payment->forceFill([
            'tripay_reference' => $result['reference'] ?? null,
            'tripay_checkout_url' => $result['checkout_url'] ?? null,
            'tripay_payload' => $result,
        ])->save();

        return $payment->fresh();
    }

    public function initiateXendit(Invoice $invoice, ?User $actor = null, ?string $paymentMethod = null): InvoicePayment
    {
        if (! $invoice->isOpen() || $invoice->status === 'draft') {
            throw new RuntimeException('Invoice tidak menerima pembayaran.');
        }

        $remaining = $invoice->remainingAmount();
        if (bccomp($remaining, '0', 2) !== 1) {
            throw new RuntimeException('Invoice sudah lunas.');
        }

        $invoice->loadMissing('tenant');
        $merchantRef = (string) Str::ulid();
        $client = $this->xendit ?? app(XenditClient::class);
        $selectedMethod = $paymentMethod ?: $client->getDefaultMethod();

        $payment = InvoicePayment::query()->create([
            'public_id' => (string) Str::ulid(),
            'invoice_id' => $invoice->row_id,
            'tenant_id' => $invoice->tenant_id,
            'method' => 'xendit',
            'status' => 'pending',
            'amount' => $remaining,
            'reference' => $merchantRef,
            'recorded_by' => $actor?->row_id,
        ]);

        $customerName = $invoice->tenant?->name ?: 'Tenant';
        $result = $client->createTransaction(
            amount: (int) round((float) $remaining),
            merchantRef: $merchantRef,
            customerName: $customerName,
            customerEmail: null,
            orderItems: [[
                'name' => $invoice->description ?: $invoice->number,
                'price' => (int) round((float) $remaining),
                'quantity' => 1,
            ]],
            paymentMethod: $selectedMethod,
        );

        $payment->forceFill([
            'tripay_reference' => $result['reference'] ?? null,
            'tripay_checkout_url' => $result['checkout_url'] ?? null,
            'tripay_payload' => $result,
        ])->save();

        return $payment->fresh();
    }

    public function checkAndSyncStatus(InvoicePayment $payment): InvoicePayment
    {
        if ($payment->status === 'paid') {
            return $payment;
        }

        if ($payment->method === 'xendit' && $payment->tripay_reference !== null) {
            $client = $this->xendit ?? app(XenditClient::class);
            $detail = $client->checkTransactionStatus($payment->tripay_reference);
            if ($detail !== null && isset($detail['status'])) {
                $this->handleXenditCallback(array_merge($payment->tripay_payload ?? [], $detail, [
                    'external_id' => $payment->reference ?: ($detail['external_id'] ?? ''),
                ]));

                return $payment->fresh();
            }
        } elseif ($payment->method === 'duitku' && $payment->reference !== null) {
            $client = $this->duitku ?? app(DuitkuClient::class);
            $detail = $client->checkTransactionStatus($payment->reference);
            if ($detail !== null && isset($detail['statusCode'])) {
                $this->handleDuitkuCallback(array_merge($payment->tripay_payload ?? [], $detail, [
                    'merchantOrderId' => $payment->reference,
                    'resultCode' => $detail['statusCode'],
                ]));

                return $payment->fresh();
            }
        } elseif ($payment->tripay_reference !== null) {
            $detail = $this->tripay->checkTransactionStatus($payment->tripay_reference);
            if ($detail !== null && isset($detail['status'])) {
                $this->handleTripayCallback(array_merge($payment->tripay_payload ?? [], $detail));

                return $payment->fresh();
            }
        }

        return $payment;
    }

    public function handleTripayCallback(array $payload): void
    {
        $merchantRef = (string) ($payload['merchant_ref'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));
        $tripayRef = (string) ($payload['reference'] ?? '');

        if ($merchantRef === '') {
            throw new RuntimeException('merchant_ref kosong.');
        }

        $payment = InvoicePayment::query()
            ->where(function ($query) use ($merchantRef, $tripayRef): void {
                $query->where('reference', $merchantRef);
                if ($tripayRef !== '') {
                    $query->orWhere('tripay_reference', $tripayRef);
                }
            })
            ->first();

        if ($payment === null) {
            throw new RuntimeException('Pembayaran tidak ditemukan.');
        }

        if ($payment->status === 'paid') {
            return;
        }

        DB::connection('platform')->transaction(function () use ($payment, $payload, $status, $tripayRef): void {
            $payment = InvoicePayment::query()->lockForUpdate()->find($payment->row_id);
            if ($payment === null || $payment->status === 'paid') {
                return;
            }

            $mapped = match ($status) {
                'PAID' => 'paid',
                'EXPIRED' => 'expired',
                'FAILED' => 'failed',
                'REFUND' => 'cancelled',
                default => 'pending',
            };

            $payment->forceFill([
                'status' => $mapped,
                'tripay_reference' => $tripayRef !== '' ? $tripayRef : $payment->tripay_reference,
                'tripay_payload' => array_merge($payment->tripay_payload ?? [], $payload),
                'paid_at' => $mapped === 'paid' ? now() : $payment->paid_at,
            ])->save();

            if ($mapped !== 'paid') {
                return;
            }

            $invoice = Invoice::query()->lockForUpdate()->find($payment->invoice_id);
            if ($invoice === null) {
                return;
            }

            $invoice->forceFill([
                'amount_paid' => bcadd((string) $invoice->amount_paid, (string) $payment->amount, 2),
            ])->save();

            $this->invoices->refreshStatus($invoice->fresh());
        });
    }

    public function handleDuitkuCallback(array $payload): void
    {
        $merchantRef = (string) ($payload['merchantOrderId'] ?? '');
        $resultCode = (string) ($payload['resultCode'] ?? '');
        $duitkuRef = (string) ($payload['reference'] ?? '');

        if ($merchantRef === '') {
            throw new RuntimeException('merchantOrderId kosong.');
        }

        $payment = InvoicePayment::query()
            ->where(function ($query) use ($merchantRef, $duitkuRef): void {
                $query->where('reference', $merchantRef);
                if ($duitkuRef !== '') {
                    $query->orWhere('tripay_reference', $duitkuRef);
                }
            })
            ->first();

        if ($payment === null) {
            throw new RuntimeException('Pembayaran tidak ditemukan.');
        }

        if ($payment->status === 'paid') {
            return;
        }

        DB::connection('platform')->transaction(function () use ($payment, $payload, $resultCode, $duitkuRef): void {
            $payment = InvoicePayment::query()->lockForUpdate()->find($payment->row_id);
            if ($payment === null || $payment->status === 'paid') {
                return;
            }

            $mapped = $resultCode === '00' ? 'paid' : 'failed';

            $payment->forceFill([
                'status' => $mapped,
                'tripay_reference' => $duitkuRef !== '' ? $duitkuRef : $payment->tripay_reference,
                'tripay_payload' => array_merge($payment->tripay_payload ?? [], $payload),
                'paid_at' => $mapped === 'paid' ? now() : $payment->paid_at,
            ])->save();

            if ($mapped !== 'paid') {
                return;
            }

            $invoice = Invoice::query()->lockForUpdate()->find($payment->invoice_id);
            if ($invoice === null) {
                return;
            }

            $invoice->forceFill([
                'amount_paid' => bcadd((string) $invoice->amount_paid, (string) $payment->amount, 2),
            ])->save();

            $this->invoices->refreshStatus($invoice->fresh());
        });
    }

    public function handleXenditCallback(array $payload): void
    {
        $merchantRef = (string) ($payload['external_id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));
        $xenditRef = (string) ($payload['id'] ?? '');

        if ($merchantRef === '') {
            throw new RuntimeException('external_id kosong.');
        }

        $payment = InvoicePayment::query()
            ->where(function ($query) use ($merchantRef, $xenditRef): void {
                $query->where('reference', $merchantRef);
                if ($xenditRef !== '') {
                    $query->orWhere('tripay_reference', $xenditRef);
                }
            })
            ->first();

        if ($payment === null) {
            throw new RuntimeException('Pembayaran tidak ditemukan.');
        }

        if ($payment->status === 'paid') {
            return;
        }

        DB::connection('platform')->transaction(function () use ($payment, $payload, $status, $xenditRef): void {
            $payment = InvoicePayment::query()->lockForUpdate()->find($payment->row_id);
            if ($payment === null || $payment->status === 'paid') {
                return;
            }

            $mapped = in_array($status, ['PAID', 'SETTLED'], true) ? 'paid' : ($status === 'EXPIRED' ? 'expired' : 'failed');

            $payment->forceFill([
                'status' => $mapped,
                'tripay_reference' => $xenditRef !== '' ? $xenditRef : $payment->tripay_reference,
                'tripay_payload' => array_merge($payment->tripay_payload ?? [], $payload),
                'paid_at' => $mapped === 'paid' ? now() : $payment->paid_at,
            ])->save();

            if ($mapped !== 'paid') {
                return;
            }

            $invoice = Invoice::query()->lockForUpdate()->find($payment->invoice_id);
            if ($invoice === null) {
                return;
            }

            $invoice->forceFill([
                'amount_paid' => bcadd((string) $invoice->amount_paid, (string) $payment->amount, 2),
            ])->save();

            $this->invoices->refreshStatus($invoice->fresh());
        });
    }
}
