<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Membership\LegacyMembershipDiscovery;
use Illuminate\Console\Command;

final class DiscoverLegacyMembership extends Command
{
    protected $signature = 'legacy:discover-membership
        {--suffix= : Filter to one lokasi suffix}
        {--json : Machine-readable JSON output}';

    protected $description = 'List legacy anggota_*/kelompok_* tables, columns, counts (read-only).';

    public function handle(LegacyMembershipDiscovery $discovery): int
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
            $this->warn('No anggota_*/kelompok_* tables found (or suffix filter empty).');

            return self::SUCCESS;
        }

        $this->table(
            ['suffix', 'anggota', 'a_count', 'a_min', 'a_max', 'kelompok', 'k_count', 'k_min', 'k_max'],
            array_map(static fn (array $r): array => [
                $r['suffix'],
                $r['anggota_exists'] ? $r['anggota_table'] : '—',
                $r['anggota_count'] ?? '—',
                $r['anggota_min_id'] ?? '—',
                $r['anggota_max_id'] ?? '—',
                $r['kelompok_exists'] ? $r['kelompok_table'] : '—',
                $r['kelompok_count'] ?? '—',
                $r['kelompok_min_id'] ?? '—',
                $r['kelompok_max_id'] ?? '—',
            ], $rows),
        );

        foreach ($rows as $r) {
            if ($r['anggota_columns'] !== []) {
                $this->newLine();
                $this->info("Columns {$r['anggota_table']}:");
                $this->line(implode(', ', array_column($r['anggota_columns'], 'name')));
            }
            if ($r['kelompok_columns'] !== []) {
                $this->newLine();
                $this->info("Columns {$r['kelompok_table']}:");
                $this->line(implode(', ', array_column($r['kelompok_columns'], 'name')));
            }
            if (is_array($r['anggota_sample'])) {
                $this->newLine();
                $this->info("Sample {$r['anggota_table']}:");
                $this->line(json_encode($r['anggota_sample'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            if (is_array($r['kelompok_sample'])) {
                $this->newLine();
                $this->info("Sample {$r['kelompok_table']}:");
                $this->line(json_encode($r['kelompok_sample'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
        }

        $this->info('Read-only discover complete. Source DB: '.config('database.connections.legacy.database'));

        return self::SUCCESS;
    }
}
