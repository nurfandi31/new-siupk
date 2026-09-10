<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Invoice;
use App\Services\Billing\InvoiceService;
use Illuminate\Console\Command;

final class CheckOverdueSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:check-overdue {--grace-days=3 : Masa tenggang hari setelah jatuh tempo sebelum ditangguhkan}';

    protected $description = 'Pemeriksaan tagihan overdue dan penonaktifan otomatis langganan tenant';

    public function handle(InvoiceService $invoiceService): int
    {
        $graceDays = (int) $this->option('grace-days');
        $cutoffDate = now()->subDays($graceDays)->toDateString();

        $openInvoices = Invoice::query()
            ->whereIn('status', ['issued', 'partially_paid'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString())
            ->get();

        $this->info("Menemukan {$openInvoices->count()} tagihan melewati jatuh tempo.");

        foreach ($openInvoices as $invoice) {
            $invoiceService->refreshStatus($invoice);
        }

        $suspendedCount = 0;
        $overdueInvoices = Invoice::query()
            ->with(['subscription', 'tenant'])
            ->where('status', 'overdue')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $cutoffDate)
            ->whereNotNull('subscription_id')
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $subscription = $invoice->subscription;
            if ($subscription !== null && in_array($subscription->status, ['active', 'past_due'], true)) {
                $subscription->forceFill([
                    'status' => 'suspended',
                ])->save();

                $this->warn("Langganan Tenant {$invoice->tenant?->name} (#{$subscription->row_id}) ditangguhkan karena tagihan {$invoice->number} overdue.");
                $suspendedCount++;
            }
        }

        $this->info("Pemeriksaan selesai. Total langganan ditangguhkan: {$suspendedCount}.");

        return self::SUCCESS;
    }
}
