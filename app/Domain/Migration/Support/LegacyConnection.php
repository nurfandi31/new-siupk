<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Read-only facade over the legacy MySQL connection.
 */
final class LegacyConnection
{
    public function connection(): ConnectionInterface
    {
        $host = (string) config('database.connections.legacy.host', '');
        $database = (string) config('database.connections.legacy.database', '');
        $username = (string) config('database.connections.legacy.username', '');

        if ($host === '' || $database === '' || $username === '') {
            throw new RuntimeException(
                'Legacy DB is not configured. Set LEGACY_DB_HOST, LEGACY_DB_DATABASE, LEGACY_DB_USERNAME (and password in .env).'
            );
        }

        return DB::connection('legacy');
    }

    public function tableExists(string $table): bool
    {
        $this->assertSafeTableName($table);

        if ($this->connection()->getDriverName() === 'sqlite') {
            $row = $this->connection()->selectOne(
                "SELECT 1 AS ok FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1",
                [$table],
            );

            return $row !== null;
        }

        $db = (string) config('database.connections.legacy.database');

        $row = $this->connection()->selectOne(
            'SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1',
            [$db, $table],
        );

        return $row !== null;
    }

    /**
     * @return list<object>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->connection()->select($sql, $bindings);
    }

    public function selectOne(string $sql, array $bindings = []): ?object
    {
        $row = $this->connection()->selectOne($sql, $bindings);

        return $row === null ? null : $row;
    }

    public function assertSafeTableName(string $table): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("Unsafe table name [{$table}].");
        }
    }

    public function transaksiTable(string|int $suffix): string
    {
        $suffix = (string) $suffix;
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new InvalidArgumentException('Suffix must be numeric lokasi id.');
        }

        return 'transaksi_'.$suffix;
    }

    public function saldoTable(string|int $suffix): string
    {
        return $this->suffixedTable('saldo', $suffix);
    }

    public function anggotaTable(string|int $suffix): string
    {
        return $this->suffixedTable('anggota', $suffix);
    }

    public function kelompokTable(string|int $suffix): string
    {
        return $this->suffixedTable('kelompok', $suffix);
    }

    public function pinjamanKelompokTable(string|int $suffix): string
    {
        return $this->suffixedTable('pinjaman_kelompok', $suffix);
    }

    public function pinjamanAnggotaTable(string|int $suffix): string
    {
        return $this->suffixedTable('pinjaman_anggota', $suffix);
    }

    public function rencanaAngsuranTable(string|int $suffix): string
    {
        return $this->suffixedTable('rencana_angsuran', $suffix);
    }

    public function realAngsuranTable(string|int $suffix): string
    {
        return $this->suffixedTable('real_angsuran', $suffix);
    }

    /**
     * @return list<object{COLUMN_NAME: string, DATA_TYPE: string, IS_NULLABLE: string, COLUMN_KEY: string}>
     */
    public function columns(string $table): array
    {
        $this->assertSafeTableName($table);

        if ($this->connection()->getDriverName() === 'sqlite') {
            $cols = $this->connection()->select("PRAGMA table_info(\"{$table}\")");

            return array_map(static fn (object $col): object => (object) [
                'COLUMN_NAME' => $col->name,
                'DATA_TYPE' => $col->type,
                'IS_NULLABLE' => $col->notnull ? 'NO' : 'YES',
                'COLUMN_KEY' => $col->pk ? 'PRI' : '',
            ], $cols);
        }

        $db = (string) config('database.connections.legacy.database');

        return $this->connection()->select(
            'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY
             FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ORDINAL_POSITION',
            [$db, $table],
        );
    }

    public function countAll(string $table): int
    {
        $this->assertSafeTableName($table);

        $row = $this->selectOne("SELECT COUNT(*) AS c FROM `{$table}`");

        return (int) ($row->c ?? 0);
    }

    private function suffixedTable(string $prefix, string|int $suffix): string
    {
        $suffix = (string) $suffix;
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new InvalidArgumentException('Suffix must be numeric lokasi id.');
        }
        $this->assertSafeTableName($prefix.'_'.$suffix);

        return $prefix.'_'.$suffix;
    }
}
