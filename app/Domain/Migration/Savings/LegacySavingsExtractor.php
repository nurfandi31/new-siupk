<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings;

use App\Domain\Migration\Support\LegacyConnection;
use Generator;
use RuntimeException;

final class LegacySavingsExtractor
{
    public function __construct(
        private LegacyConnection $legacy,
    ) {}

    public function jenisSimpananCount(): int
    {
        $table = $this->legacy->jenisSimpananTable();

        return $this->legacy->tableExists($table) ? $this->legacy->countAll($table) : 0;
    }

    /**
     * @return list<object>
     */
    public function jenisSimpananRows(): array
    {
        $table = $this->legacy->jenisSimpananTable();
        if (! $this->legacy->tableExists($table)) {
            return [];
        }

        return $this->legacy->select("SELECT * FROM `{$table}` ORDER BY id ASC");
    }

    /**
     * @return list<int>
     */
    public function usedJenisSimpananIds(string $suffix): array
    {
        $table = $this->legacy->simpananAnggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return [];
        }

        $rows = $this->legacy->select(
            "SELECT DISTINCT jenis_simpanan AS id FROM `{$table}`
             WHERE jenis_simpanan IS NOT NULL AND jenis_simpanan > 0
             ORDER BY jenis_simpanan ASC"
        );

        return array_map(static fn (object $row): int => (int) $row->id, $rows);
    }

    public function simpananAnggotaCount(string $suffix): int
    {
        $table = $this->legacy->simpananAnggotaTable($suffix);

        return $this->legacy->tableExists($table) ? $this->legacy->countAll($table) : 0;
    }

    public function realSimpananCount(string $suffix): int
    {
        $table = $this->legacy->realSimpananTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}`
             WHERE COALESCE(real_k, 0) <> 0 OR COALESCE(real_d, 0) <> 0"
        )->c ?? 0);
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function simpananAnggotaChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->simpananAnggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $offset = 0;
        while (true) {
            $rows = $this->legacy->select(
                "SELECT * FROM `{$table}` ORDER BY id ASC LIMIT ? OFFSET ?",
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
    public function realSimpananChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->realSimpananTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $transaksiTable = $this->legacy->transaksiTable($suffix);
        $hasTransaksi = $this->legacy->tableExists($transaksiTable);
        $select = 'r.*';
        $join = '';
        if ($hasTransaksi) {
            $select .= ', t.keterangan_transaksi, t.rekening_debit, t.rekening_kredit';
            $join = " LEFT JOIN `{$transaksiTable}` t ON t.idt = r.idt";
        }

        $offset = 0;
        while (true) {
            $rows = $this->legacy->select(
                "SELECT {$select}
                 FROM `{$table}` r{$join}
                 WHERE COALESCE(r.real_k, 0) <> 0 OR COALESCE(r.real_d, 0) <> 0
                 ORDER BY r.cif ASC, r.tgl_transaksi ASC, r.id ASC
                 LIMIT ? OFFSET ?",
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
