<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Models\Platform\Invoice;
use App\Models\Platform\Subscription;

final readonly class SubscriptionGateService
{
    private const BLOCKING_INVOICE_STATUSES = ['issued', 'partially_paid', 'overdue', 'pending_payment'];

    private const BLOCKING_SUBSCRIPTION_STATUSES = ['suspended', 'past_due'];

    /**
     * @return array{
     *     blocked: bool,
     *     reason: 'invoice_block'|'subscription_suspended'|null,
     *     invoice_id: int|null,
     *     invoice_number: string|null,
     *     message: string|null,
     *     invoice: array{
     *         row_id: int,
     *         number: string,
     *         status: string,
     *         purpose: string|null,
     *         description: string|null,
     *         amount: float,
     *         amount_paid: float,
     *         remaining: float,
     *         currency: string|null,
     *         due_at: string|null
     *     }|null,
     *     subscription_status: string|null
     * }
     */
    public function check(int $tenantRowId): array
    {
        $blockingInvoice = Invoice::query()
            ->where('tenant_id', $tenantRowId)
            ->where('blocks_access', true)
            ->whereIn('status', self::BLOCKING_INVOICE_STATUSES)
            ->oldest('due_at')
            ->first();

        if ($blockingInvoice !== null) {
            return [
                'blocked' => true,
                'reason' => 'invoice_block',
                'invoice_id' => (int) $blockingInvoice->row_id,
                'invoice_number' => (string) $blockingInvoice->number,
                'message' => "Akses fitur operasional ditangguhkan sementara karena tagihan #{$blockingInvoice->number} ({$blockingInvoice->description}) memblokir akses sampai diselesaikan.",
                'invoice' => $this->invoicePayload($blockingInvoice),
                'subscription_status' => null,
            ];
        }

        $subscription = Subscription::query()
            ->where('tenant_id', $tenantRowId)
            ->latest('row_id')
            ->first();

        if ($subscription !== null && in_array($subscription->status, self::BLOCKING_SUBSCRIPTION_STATUSES, true)) {
            return [
                'blocked' => true,
                'reason' => 'subscription_suspended',
                'invoice_id' => null,
                'invoice_number' => null,
                'message' => 'Langganan aplikasi Anda sedang ditangguhkan karena tagihan melewati jatuh tempo. Silakan lakukan pembayaran tagihan pada menu Tagihan/Billing.',
                'invoice' => null,
                'subscription_status' => (string) $subscription->status,
            ];
        }

        return [
            'blocked' => false,
            'reason' => null,
            'invoice_id' => null,
            'invoice_number' => null,
            'message' => null,
            'invoice' => null,
            'subscription_status' => $subscription?->status,
        ];
    }

    /**
     * @return array{
     *     row_id: int,
     *     number: string,
     *     status: string,
     *     purpose: string|null,
     *     description: string|null,
     *     amount: float,
     *     amount_paid: float,
     *     remaining: float,
     *     currency: string|null,
     *     due_at: string|null
     * }
     */
    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'row_id' => (int) $invoice->row_id,
            'number' => (string) $invoice->number,
            'status' => (string) $invoice->status,
            'purpose' => $invoice->purpose !== null ? (string) $invoice->purpose : null,
            'description' => $invoice->description !== null ? (string) $invoice->description : null,
            'amount' => (float) $invoice->amount,
            'amount_paid' => (float) $invoice->amount_paid,
            'remaining' => (float) $invoice->remainingAmount(),
            'currency' => $invoice->currency !== null ? (string) $invoice->currency : null,
            'due_at' => $invoice->due_at?->toDateString(),
        ];
    }
}
