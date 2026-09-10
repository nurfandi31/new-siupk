<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Lending\LegacyLoanLoader;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use RuntimeException;

/** Re-apply payment → installment FIFO progress (no re-import). */
final class ApplyLegacyLoanPaymentProgress extends Command
{
    protected $signature = 'legacy:apply-loan-payment-progress {tenant : Tenant code or id}';

    protected $description = 'FIFO-apply loan_payments onto loan_installments paid fields for a tenant.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        LegacyLoanLoader $loader,
    ): int {
        $tenant = Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit((string) $this->argument('tenant')),
                fn ($q) => $q->whereKey((int) $this->argument('tenant')),
                fn ($q) => $q->where('code', (string) $this->argument('tenant')),
            )
            ->firstOrFail();

        $placement = $tenant->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $n = $loader->applyPaymentProgressToInstallments();
            $this->info("Updated installment progress for {$n} loans (tenant={$tenant->code}).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $context->clear();
            $connections->disconnect();
        }
    }
}
