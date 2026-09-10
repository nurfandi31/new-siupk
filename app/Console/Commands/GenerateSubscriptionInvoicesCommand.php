<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Invoice;
use App\Models\Platform\Subscription;
use App\Services\Billing\InvoiceService;
use Illuminate\Console\Command;

final class GenerateSubscriptionInvoicesCommand extends Command
{
    protected $signature = 'subscriptions:generate-invoices {--days=7 : Hari sebelum tanggal berakhir untuk membuat invoice}';

    protected $description = 'Otomatisasi pembuatan tagihan (invoice) bagi langganan aktif yang mendekati jatuh tempo';

    public function handle(InvoiceService $invoiceService): int
    {
        $days = (int) $this->option('days');
        $targetDate = now()->addDays($days)->toDateString();

        $subscriptions = Subscription::query()
            ->with(['tenant', 'plan'])
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $targetDate)
            ->get();

        $this->info("Menemukan {$subscriptions->count()} langganan aktif yang memerlukan perpanjangan.");

        $generated = 0;
        foreach ($subscriptions as $subscription) {
            $hasOpenInvoice = Invoice::query()
                ->where('subscription_id', $subscription->row_id)
                ->whereIn('status', ['issued', 'partially_paid', 'draft'])
                ->exists();

            if ($hasOpenInvoice) {
                $this->line("Subscription #{$subscription->row_id} (Tenant: {$subscription->tenant?->name}) sudah memiliki invoice aktif/terbuka. Dilewati.");

                continue;
            }

            try {
                $invoice = $invoiceService->generateFromSubscription($subscription);
                $this->info("Invoice {$invoice->number} berhasil dibuat untuk {$subscription->tenant?->name} (Rp {$invoice->amount}).");
                $generated++;
            } catch (\Throwable $e) {
                $this->error("Gagal membuat invoice untuk subscription #{$subscription->row_id}: {$e->getMessage()}");
            }
        }

        $this->info("Selesai. Total invoice baru dibuat: {$generated}.");

        return self::SUCCESS;
    }
}
