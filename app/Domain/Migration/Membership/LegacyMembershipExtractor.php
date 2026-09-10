<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Support\LegacyConnection;
use Generator;
use RuntimeException;

final class LegacyMembershipExtractor
{
    public function __construct(
        private LegacyConnection $legacy,
    ) {}

    public function activeAnggotaCount(string $suffix): int
    {
        $table = $this->legacy->anggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        $hasDeleted = $this->hasColumn($table, 'deleted_at');
        $sql = $hasDeleted
            ? "SELECT COUNT(*) AS c FROM `{$table}` WHERE deleted_at IS NULL"
            : "SELECT COUNT(*) AS c FROM `{$table}`";

        return (int) ($this->legacy->selectOne($sql)->c ?? 0);
    }

    public function activeKelompokCount(string $suffix): int
    {
        $table = $this->legacy->kelompokTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        $hasDeleted = $this->hasColumn($table, 'deleted_at');
        $sql = $hasDeleted
            ? "SELECT COUNT(*) AS c FROM `{$table}` WHERE deleted_at IS NULL"
            : "SELECT COUNT(*) AS c FROM `{$table}`";

        return (int) ($this->legacy->selectOne($sql)->c ?? 0);
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function anggotaChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->anggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $idCol = $this->idColumn($table, ['id', 'id_anggota', 'ida']);
        $hasDeleted = $this->hasColumn($table, 'deleted_at');
        $offset = 0;

        while (true) {
            $sql = "SELECT * FROM `{$table}`";
            if ($hasDeleted) {
                $sql .= ' WHERE deleted_at IS NULL';
            }
            $sql .= " ORDER BY `{$idCol}` ASC LIMIT ? OFFSET ?";
            $rows = $this->legacy->select($sql, [$chunk, $offset]);
            if ($rows === []) {
                break;
            }
            yield $rows;
            if (count($rows) < $chunk) {
                break;
            }
            $offset += $chunk;
        }
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function kelompokChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->kelompokTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $idCol = $this->idColumn($table, ['id', 'id_kelompok', 'idk']);
        $hasDeleted = $this->hasColumn($table, 'deleted_at');
        $offset = 0;

        while (true) {
            $sql = "SELECT * FROM `{$table}`";
            if ($hasDeleted) {
                $sql .= ' WHERE deleted_at IS NULL';
            }
            $sql .= " ORDER BY `{$idCol}` ASC LIMIT ? OFFSET ?";
            $rows = $this->legacy->select($sql, [$chunk, $offset]);
            if ($rows === []) {
                break;
            }
            yield $rows;
            if (count($rows) < $chunk) {
                break;
            }
            $offset += $chunk;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        foreach ($this->legacy->columns($table) as $c) {
            if (strcasecmp((string) $c->COLUMN_NAME, $column) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function idColumn(string $table, array $candidates): string
    {
        $names = array_map(
            static fn (object $c): string => (string) $c->COLUMN_NAME,
            $this->legacy->columns($table),
        );
        foreach ($candidates as $c) {
            foreach ($names as $n) {
                if (strcasecmp($n, $c) === 0) {
                    return $n;
                }
            }
        }

        return $names[0] ?? 'id';
    }
}
