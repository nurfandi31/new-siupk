<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending;

use App\Domain\Migration\Support\LegacyConnection;
use Generator;
use RuntimeException;

final class LegacyLendingExtractor
{
    public function __construct(
        private LegacyConnection $legacy,
    ) {}

    public function pinjamanKelompokCount(string $suffix): int
    {
        $table = $this->legacy->pinjamanKelompokTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        return $this->legacy->countAll($table);
    }

    public function pinjamanAnggotaCount(string $suffix): int
    {
        $table = $this->legacy->pinjamanAnggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        return $this->legacy->countAll($table);
    }

    public function rencanaCount(string $suffix): int
    {
        $table = $this->legacy->rencanaAngsuranTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE CAST(angsuran_ke AS UNSIGNED) > 0"
        )->c ?? 0);
    }

    public function realCount(string $suffix): int
    {
        $table = $this->legacy->realAngsuranTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        // Match loader: id>0 and not both realisasi zero (placeholders).
        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}`
             WHERE id > 0
               AND NOT (
                 COALESCE(realisasi_pokok, 0) = 0
                 AND COALESCE(realisasi_jasa, 0) = 0
               )"
        )->c ?? 0);
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function pinjamanKelompokChunks(string $suffix, int $chunk): Generator
    {
        yield from $this->chunked($this->legacy->pinjamanKelompokTable($suffix), 'id', $chunk);
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function pinjamanAnggotaChunks(string $suffix, int $chunk): Generator
    {
        yield from $this->chunked($this->legacy->pinjamanAnggotaTable($suffix), 'id', $chunk);
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function rencanaChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->rencanaAngsuranTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $offset = 0;
        while (true) {
            $rows = $this->legacy->select(
                "SELECT * FROM `{$table}`
                 WHERE CAST(angsuran_ke AS UNSIGNED) > 0
                 ORDER BY id ASC LIMIT ? OFFSET ?",
                [$chunk, $offset],
            );
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
    public function realChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->realAngsuranTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $offset = 0;
        while (true) {
            $rows = $this->legacy->select(
                "SELECT * FROM `{$table}`
                 WHERE id > 0
                   AND NOT (
                     COALESCE(realisasi_pokok, 0) = 0
                     AND COALESCE(realisasi_jasa, 0) = 0
                   )
                 ORDER BY id ASC LIMIT ? OFFSET ?",
                [$chunk, $offset],
            );
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
    private function chunked(string $table, string $idCol, int $chunk): Generator
    {
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $offset = 0;
        while (true) {
            $rows = $this->legacy->select(
                "SELECT * FROM `{$table}` ORDER BY `{$idCol}` ASC LIMIT ? OFFSET ?",
                [$chunk, $offset],
            );
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
}
