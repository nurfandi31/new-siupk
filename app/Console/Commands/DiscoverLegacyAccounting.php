<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Accounting\LegacyAccountingDiscovery;
use Illuminate\Console\Command;

final class DiscoverLegacyAccounting extends Command
{
    protected $signature = 'legacy:discover-accounting
        {--suffix= : Filter to one lokasi suffix}
        {--json : Machine-readable JSON output}';

    protected $description = 'List legacy transaksi_*/saldo_* tables and row counts (read-only).';

    public function handle(LegacyAccountingDiscovery $discovery): int
    {
        $suffix = $this->option('suffix');
        $suffix = is_string($suffix) && $suffix !== '' ? $suffix : null;

        try {
            $rows = $discovery->discover($suffix);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->warn('No transaksi_*/saldo_* tables found (or suffix filter empty).');

            return self::SUCCESS;
        }

        $this->table(
            ['suffix', 'transaksi', 'count', 'min_date', 'max_date', 'min_idt', 'max_idt', 'saldo', 'bulan0'],
            array_map(static fn (array $r): array => [
                $r['suffix'],
                $r['transaksi_exists'] ? $r['transaksi_table'] : '—',
                $r['transaksi_count'] ?? '—',
                $r['min_date'] ?? '—',
                $r['max_date'] ?? '—',
                $r['min_idt'] ?? '—',
                $r['max_idt'] ?? '—',
                $r['saldo_exists'] ? $r['saldo_table'] : '—',
                $r['saldo_bulan0'] ?? '—',
            ], $rows),
        );

        $this->info('Read-only discover complete. Source DB: '.config('database.connections.legacy.database'));

        return self::SUCCESS;
    }
}
