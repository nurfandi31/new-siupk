<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Migration\Support\LegacyConnection;

final class LegacyAccountingDiscovery
{
    public function __construct(
        private LegacyConnection $legacy,
    ) {}

    /**
     * @return list<array{
     *   suffix: string,
     *   transaksi_table: string,
     *   saldo_table: string,
     *   transaksi_exists: bool,
     *   saldo_exists: bool,
     *   transaksi_count: int|null,
     *   min_date: string|null,
     *   max_date: string|null,
     *   min_idt: int|null,
     *   max_idt: int|null,
     *   saldo_bulan0: int|null
     * }>
     */
    public function discover(?string $suffixFilter = null): array
    {
        $db = (string) config('database.connections.legacy.database');
        if ($this->legacy->connection()->getDriverName() === 'sqlite') {
            $rows = $this->legacy->select(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND (name LIKE 'transaksi_%' OR name LIKE 'saldo_%') ORDER BY name"
            );
        } else {
            $rows = $this->legacy->select(
                "SELECT table_name AS name
                 FROM information_schema.tables
                 WHERE table_schema = ?
                   AND (table_name LIKE 'transaksi\\_%' OR table_name LIKE 'saldo\\_%')
                 ORDER BY table_name",
                [$db],
            );
        }

        $suffixes = [];
        foreach ($rows as $row) {
            $name = (string) $row->name;
            if (preg_match('/^(transaksi|saldo)_(\d+)$/', $name, $m) !== 1) {
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
            $trx = $this->legacy->transaksiTable($suffix);
            $saldo = $this->legacy->saldoTable($suffix);
            $trxExists = $this->legacy->tableExists($trx);
            $saldoExists = $this->legacy->tableExists($saldo);

            $item = [
                'suffix' => (string) $suffix,
                'transaksi_table' => $trx,
                'saldo_table' => $saldo,
                'transaksi_exists' => $trxExists,
                'saldo_exists' => $saldoExists,
                'transaksi_count' => null,
                'min_date' => null,
                'max_date' => null,
                'min_idt' => null,
                'max_idt' => null,
                'saldo_bulan0' => null,
            ];

            if ($trxExists) {
                $hasDeletedAt = false;
                foreach ($this->legacy->columns($trx) as $col) {
                    if ((string) ($col->COLUMN_NAME ?? '') === 'deleted_at') {
                        $hasDeletedAt = true;
                        break;
                    }
                }
                $statsQuery = "SELECT COUNT(*) AS c,
                            MIN(tgl_transaksi) AS min_date,
                            MAX(tgl_transaksi) AS max_date,
                            MIN(idt) AS min_idt,
                            MAX(idt) AS max_idt
                     FROM `{$trx}`";
                if ($hasDeletedAt) {
                    $statsQuery .= ' WHERE deleted_at IS NULL';
                }
                $stats = $this->legacy->selectOne($statsQuery);
                $item['transaksi_count'] = (int) ($stats->c ?? 0);
                $item['min_date'] = $stats->min_date !== null ? (string) $stats->min_date : null;
                $item['max_date'] = $stats->max_date !== null ? (string) $stats->max_date : null;
                $item['min_idt'] = $stats->min_idt !== null ? (int) $stats->min_idt : null;
                $item['max_idt'] = $stats->max_idt !== null ? (int) $stats->max_idt : null;
            }

            if ($saldoExists) {
                $s0 = $this->legacy->selectOne(
                    "SELECT COUNT(*) AS c FROM `{$saldo}` WHERE CAST(bulan AS UNSIGNED) = 0",
                );
                $item['saldo_bulan0'] = (int) ($s0->c ?? 0);
            }

            $out[] = $item;
        }

        return $out;
    }
}
