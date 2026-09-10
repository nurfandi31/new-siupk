<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Platform\Invoice;
use App\Models\Platform\Subscription;
use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class InvoiceService
{
    public function create(Tenant $tenant, array $data, ?User $actor = null): Invoice
    {
        return DB::connection('platform')->transaction(function () use ($tenant, $data, $actor): Invoice {
            $status = $data['status'] ?? 'issued';
            $issuedAt = $status === 'draft' ? null : now();

            return Invoice::query()->create([
                'public_id' => (string) Str::ulid(),
                'number' => $this->nextNumber(),
                'tenant_id' => $tenant->row_id,
                'subscription_id' => $data['subscription_id'] ?? null,
                'purpose' => $data['purpose'] ?? (($data['subscription_id'] ?? null) ? 'subscription' : 'other'),
                'status' => $status,
                'blocks_access' => (bool) ($data['blocks_access'] ?? false),
                'amount' => $data['amount'],
                'amount_paid' => 0,
                'currency' => $data['currency'] ?? 'IDR',
                'issued_at' => $issuedAt,
                'due_at' => $data['due_at'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->row_id,
            ]);
        });
    }

    public function generateFromSubscription(Subscription $subscription, ?User $actor = null): Invoice
    {
        $subscription->loadMissing('plan', 'tenant');
        $plan = $subscription->plan;
        if ($plan === null) {
            throw new RuntimeException('Subscription tidak memiliki plan.');
        }

        $periodLabel = $plan->billing_period === 'yearly' ? 'tahunan' : 'bulanan';

        return $this->create($subscription->tenant, [
            'subscription_id' => $subscription->row_id,
            'purpose' => 'subscription',
            'amount' => $plan->price_amount,
            'currency' => $plan->currency ?: 'IDR',
            'due_at' => now()->addDays(14)->toDateString(),
            'description' => "Langganan {$plan->name} ({$periodLabel})",
            'status' => 'issued',
            'blocks_access' => false,
        ], $actor);
    }

    public function void(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, ['paid', 'void'], true)) {
            throw new RuntimeException('Invoice tidak dapat dibatalkan.');
        }

        if (bccomp((string) $invoice->amount_paid, '0', 2) === 1) {
            throw new RuntimeException('Invoice yang sudah dibayar sebagian tidak dapat dibatalkan.');
        }

        $invoice->forceFill(['status' => 'void'])->save();

        return $invoice->fresh();
    }

    public function refreshStatus(Invoice $invoice): Invoice
    {
        $paid = (string) $invoice->amount_paid;
        $amount = (string) $invoice->amount;

        if (bccomp($paid, '0', 2) === 0) {
            $status = $invoice->status === 'draft' ? 'draft' : 'issued';
            if ($invoice->due_at !== null && $invoice->due_at->isPast() && $status === 'issued') {
                $status = 'overdue';
            }
            $invoice->forceFill(['status' => $status, 'paid_at' => null])->save();

            return $invoice->fresh();
        }

        if (bccomp($paid, $amount, 2) >= 0) {
            $invoice->forceFill([
                'status' => 'paid',
                'amount_paid' => $amount,
                'paid_at' => $invoice->paid_at ?? now(),
            ])->save();

            $fresh = $invoice->fresh();
            if ($fresh->subscription_id !== null) {
                $subscription = Subscription::query()->find($fresh->subscription_id);
                if ($subscription !== null) {
                    app(SubscriptionService::class)->renewFromPaidInvoice($subscription, $fresh);
                }
            }

            return $fresh;
        }

        $invoice->forceFill([
            'status' => 'partially_paid',
            'paid_at' => null,
        ])->save();

        return $invoice->fresh();
    }

    private function nextNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';

        $last = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $seq = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
