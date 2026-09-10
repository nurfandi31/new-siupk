<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantRegistrySynchronizer;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BackfillJournalNumbers extends Command
{
    protected $signature = 'accounting:backfill-journal-numbers
        {tenant : Tenant row ID or code}
        {--dry-run : Display what would be generated without writing}';

    protected $description = 'Backfill journal_number (YYMM-NNN) for posted journals that have no journal_number yet.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        TenantRegistrySynchronizer $registry,
        TenantSequenceService $sequences,
    ): int {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            $this->error('Tenant placement is incomplete.');

            return self::FAILURE;
        }

        $connections->connect($shard);

        try {
            $registry->sync($tenant);
            $context->initialize($tenant, $placement, $shard);

            $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
            $db = DB::connection($connectionName);

            $entries = $db->table('journal_entries')
                ->where('tenant_id', $tenant->row_id)
                ->where('status', 'posted')
                ->where(function ($q) {
                    $q->whereNull('journal_number')
                        ->orWhere('journal_number', '');
                })
                ->orderBy('transaction_date')
                ->orderBy('row_id')
                ->get(['row_id', 'id', 'transaction_date', 'source_type']);

            if ($entries->isEmpty()) {
                $this->info('No journals need backfilling.');

                return self::SUCCESS;
            }

            $this->info(sprintf('Found %d journal(s) to backfill.', $entries->count()));

            $filled = 0;
            foreach ($entries as $entry) {
                if ($entry->source_type === 'legacy_transaksi') {
                    $journalNumber = (string) $entry->id;
                } else {
                    $date = $entry->transaction_date;
                    $prefix = date('ym', strtotime($date));
                    $sequenceName = 'journal_number:'.$prefix;

                    $seq = $sequences->next($sequenceName);
                    $journalNumber = $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
                }

                $this->line(sprintf(
                    '  #%d  %s  ->  %s%s',
                    $entry->id,
                    $entry->transaction_date,
                    $journalNumber,
                    $entry->source_type === 'legacy_transaksi' ? ' (legacy)' : '',
                ));

                if (! $this->option('dry-run')) {
                    $db->table('journal_entries')
                        ->where('row_id', $entry->row_id)
                        ->where('tenant_id', $tenant->row_id)
                        ->update(['journal_number' => $journalNumber]);
                }

                $filled++;
            }

            $this->info(sprintf(
                '%s %d journal number(s).',
                $this->option('dry-run') ? 'Would backfill' : 'Backfilled',
                $filled,
            ));

            return self::SUCCESS;
        } finally {
            $context->clear();
            $connections->disconnect();
        }
    }

    private function resolveTenant(string $value): Tenant
    {
        return Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('code', $value),
            )
            ->firstOrFail();
    }
}
