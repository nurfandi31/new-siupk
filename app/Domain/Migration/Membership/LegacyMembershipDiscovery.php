<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Support\LegacyConnection;

final class LegacyMembershipDiscovery
{
    public function __construct(
        private LegacyConnection $legacy,
    ) {}

    /**
     * @return list<array{
     *   suffix: string,
     *   anggota_table: string,
     *   kelompok_table: string,
     *   anggota_exists: bool,
     *   kelompok_exists: bool,
     *   anggota_count: int|null,
     *   kelompok_count: int|null,
     *   anggota_min_id: int|null,
     *   anggota_max_id: int|null,
     *   kelompok_min_id: int|null,
     *   kelompok_max_id: int|null,
     *   anggota_columns: list<array{name: string, type: string, nullable: string, key: string}>,
     *   kelompok_columns: list<array{name: string, type: string, nullable: string, key: string}>,
     *   anggota_sample: array<string, mixed>|null,
     *   kelompok_sample: array<string, mixed>|null
     * }>
     */
    public function discover(?string $suffixFilter = null): array
    {
        $db = (string) config('database.connections.legacy.database');
        if ($this->legacy->connection()->getDriverName() === 'sqlite') {
            $rows = $this->legacy->select(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND (name LIKE 'anggota_%' OR name LIKE 'kelompok_%') ORDER BY name"
            );
        } else {
            $rows = $this->legacy->select(
                "SELECT table_name AS name
                 FROM information_schema.tables
                 WHERE table_schema = ?
                   AND (table_name LIKE 'anggota\\_%' OR table_name LIKE 'kelompok\\_%')
                 ORDER BY table_name",
                [$db],
            );
        }

        $suffixes = [];
        foreach ($rows as $row) {
            $name = (string) $row->name;
            if (preg_match('/^(anggota|kelompok)_(\d+)$/', $name, $m) !== 1) {
                continue;
            }
            $suffix = $m[2];
            if ($suffixFilter !== null && $suffixFilter !== '' && $suffix !== (string) $suffixFilter) {
                continue;
            }
            $suffixes[$suffix] = true;
        }

        ksort($suffixes, SORT_NUMERIC);

        $out = [];
        foreach (array_keys($suffixes) as $suffix) {
            $anggota = $this->legacy->anggotaTable($suffix);
            $kelompok = $this->legacy->kelompokTable($suffix);
            $anggotaExists = $this->legacy->tableExists($anggota);
            $kelompokExists = $this->legacy->tableExists($kelompok);

            $item = [
                'suffix' => (string) $suffix,
                'anggota_table' => $anggota,
                'kelompok_table' => $kelompok,
                'anggota_exists' => $anggotaExists,
                'kelompok_exists' => $kelompokExists,
                'anggota_count' => null,
                'kelompok_count' => null,
                'anggota_min_id' => null,
                'anggota_max_id' => null,
                'kelompok_min_id' => null,
                'kelompok_max_id' => null,
                'anggota_columns' => [],
                'kelompok_columns' => [],
                'anggota_sample' => null,
                'kelompok_sample' => null,
            ];

            if ($anggotaExists) {
                $item['anggota_columns'] = $this->mapColumns($anggota);
                $item['anggota_count'] = $this->legacy->countAll($anggota);
                $idCol = $this->guessIdColumn($item['anggota_columns']);
                if ($idCol !== null) {
                    $stats = $this->legacy->selectOne(
                        "SELECT MIN(`{$idCol}`) AS min_id, MAX(`{$idCol}`) AS max_id FROM `{$anggota}`"
                    );
                    $item['anggota_min_id'] = $stats?->min_id !== null ? (int) $stats->min_id : null;
                    $item['anggota_max_id'] = $stats?->max_id !== null ? (int) $stats->max_id : null;
                }
                $sample = $this->legacy->selectOne("SELECT * FROM `{$anggota}` LIMIT 1");
                $item['anggota_sample'] = $sample !== null ? (array) $sample : null;
            }

            if ($kelompokExists) {
                $item['kelompok_columns'] = $this->mapColumns($kelompok);
                $item['kelompok_count'] = $this->legacy->countAll($kelompok);
                $idCol = $this->guessIdColumn($item['kelompok_columns']);
                if ($idCol !== null) {
                    $stats = $this->legacy->selectOne(
                        "SELECT MIN(`{$idCol}`) AS min_id, MAX(`{$idCol}`) AS max_id FROM `{$kelompok}`"
                    );
                    $item['kelompok_min_id'] = $stats?->min_id !== null ? (int) $stats->min_id : null;
                    $item['kelompok_max_id'] = $stats?->max_id !== null ? (int) $stats->max_id : null;
                }
                $sample = $this->legacy->selectOne("SELECT * FROM `{$kelompok}` LIMIT 1");
                $item['kelompok_sample'] = $sample !== null ? (array) $sample : null;
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: string, key: string}>  $cols
     */
    private function guessIdColumn(array $cols): ?string
    {
        $names = array_column($cols, 'name');
        foreach (['id', 'id_anggota', 'id_kelompok', 'ida', 'idk'] as $c) {
            if (in_array($c, $names, true)) {
                return $c;
            }
        }

        return $names[0] ?? null;
    }

    /**
     * @return list<array{name: string, type: string, nullable: string, key: string}>
     */
    private function mapColumns(string $table): array
    {
        return array_map(
            static fn (object $c): array => [
                'name' => (string) $c->COLUMN_NAME,
                'type' => (string) $c->DATA_TYPE,
                'nullable' => (string) $c->IS_NULLABLE,
                'key' => (string) $c->COLUMN_KEY,
            ],
            $this->legacy->columns($table),
        );
    }
}
