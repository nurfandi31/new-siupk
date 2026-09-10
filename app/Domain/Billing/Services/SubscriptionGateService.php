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
     * @return array{blocked: bool, reason: 'invoice_block'|'subscription_suspended'|null, invoice_id: int|null, invoice_number: string|null, message: string|null}
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
            ];
        }

        return [
            'blocked' => false,
            'reason' => null,
            'invoice_id' => null,
            'invoice_number' => null,
            'message' => null,
        ];
    }
}
