<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending;

use App\Domain\Migration\Support\LegacyConnection;
use Generator;
use RuntimeException;

final class LegacyLendingExtractor
{
    /** @var array<int, string> */
    private array $jppKodeByJenis = [];

    private bool $jppKodeLoaded = false;

    /** @var array<string, array<string, true>> */
    private array $legacyChartBySuffix = [];

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

    /** @return array<int, true> */
    public function pinjamanKelompokIdSet(string $suffix): array
    {
        $table = $this->legacy->pinjamanKelompokTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return [];
        }

        $ids = [];
        foreach ($this->legacy->select("SELECT id FROM `{$table}` ORDER BY id ASC") as $row) {
            $ids[(int) $row->id] = true;
        }

        return $ids;
    }

    public function pinjamanAnggotaCount(string $suffix): int
    {
        $table = $this->legacy->pinjamanAnggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        // SIUPK menyimpan pemanfaat kelompok (jenis_pinjaman='K', punya
        // id_pinkel) dan pinjaman individu (jenis_pinjaman='I', id_pinkel=0)
        // pada tabel yang sama. Pipeline ini hanya memetakan pemanfaat
        // kelompok; baris individu tidak boleh dianggap pemanfaat.
        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE id_pinkel > 0"
        )->c ?? 0);
    }

    /**
     * Count only beneficiaries whose parent group loan still exists. Orphan
     * parent references are reported separately as an approval exception.
     */
    public function pinjamanAnggotaWithParentCount(string $suffix): int
    {
        $paTable = $this->legacy->pinjamanAnggotaTable($suffix);
        $pkTable = $this->legacy->pinjamanKelompokTable($suffix);
        if (! $this->legacy->tableExists($paTable) || ! $this->legacy->tableExists($pkTable)) {
            return 0;
        }

        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c
             FROM `{$paTable}` pa
             WHERE pa.id_pinkel > 0
               AND EXISTS (SELECT 1 FROM `{$pkTable}` pk WHERE pk.id = pa.id_pinkel)"
        )->c ?? 0);
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

    public function pinjamanIndividuCount(string $suffix): int
    {
        $table = $this->legacy->pinjamanAnggotaTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE jenis_pinjaman = 'I'"
        )->c ?? 0);
    }

    public function rencanaIndividuCount(string $suffix): int
    {
        $table = $this->legacy->rencanaAngsuranIndividuTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE CAST(angsuran_ke AS UNSIGNED) > 0"
        )->c ?? 0);
    }

    public function realIndividuCount(string $suffix): int
    {
        $table = $this->legacy->realAngsuranIndividuTable($suffix);
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        return (int) ($this->legacy->selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}`
             WHERE id > 0
               AND NOT (
                 COALESCE(realisasi_pokok, 0) = 0
                 AND COALESCE(realisasi_jasa, 0) = 0
               )"
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
        foreach ($this->chunked($this->legacy->pinjamanKelompokTable($suffix), 'id', $chunk) as $rows) {
            yield $this->withJppKode($rows, $suffix);
        }
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function pinjamanAnggotaChunks(string $suffix, int $chunk): Generator
    {
        yield from $this->chunked($this->legacy->pinjamanAnggotaTable($suffix), 'id', $chunk, 'id_pinkel > 0');
    }

    /**
     * @return Generator<int, list<object>>
     */
    public function pinjamanIndividuChunks(string $suffix, int $chunk): Generator
    {
        foreach ($this->chunked($this->legacy->pinjamanAnggotaTable($suffix), 'id', $chunk, "jenis_pinjaman = 'I'") as $rows) {
            yield $this->withJppKode($rows, $suffix);
        }
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
    public function rencanaIndividuChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->rencanaAngsuranIndividuTable($suffix);
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
    public function realIndividuChunks(string $suffix, int $chunk): Generator
    {
        $table = $this->legacy->realAngsuranIndividuTable($suffix);
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
     * Lengkapi baris pinjaman dengan kode produk legacy (`jenis_produk_pinjaman.kode`)
     * dan kode akun yang benar-benar ada di chart legacy. Pemetaan akun eksplisit
     * mencegah kode produk tanpa akun legacy jatuh ke akun Next yang kebetulan
     * memakai kode sama (mis. 1.1.03.10 = Piutang Jasa Perorangan di Next).
     *
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function withJppKode(array $rows, string $suffix): array
    {
        $map = $this->jppKodeMap();
        $chart = $this->legacyChart($suffix);

        foreach ($rows as $row) {
            $jenis = (int) ($row->jenis_pp ?? 0);
            $kode = $map[$jenis] ?? null;
            $row->jpp_kode = $kode;

            $row->jpp_principal_account = $kode !== null && isset($chart['1.1.03.'.$kode])
                ? '1.1.03.'.$kode
                : null;
            $row->jpp_revenue_account = $kode !== null && isset($chart['4.1.01.'.$kode])
                ? '4.1.01.'.$kode
                : null;
            $row->jpp_penalty_account = $kode !== null && isset($chart['4.1.02.'.$kode])
                ? '4.1.02.'.$kode
                : null;
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function jppKodeMap(): array
    {
        if ($this->jppKodeLoaded) {
            return $this->jppKodeByJenis;
        }

        $this->jppKodeLoaded = true;

        if (! $this->legacy->tableExists('jenis_produk_pinjaman')) {
            return [];
        }

        foreach ($this->legacy->select('SELECT id, kode FROM jenis_produk_pinjaman') as $row) {
            $kode = trim((string) ($row->kode ?? ''));
            if ($kode === '' || ! ctype_digit($kode)) {
                continue;
            }

            $number = (int) $kode;
            if ($number <= 0 || $number > 99) {
                continue;
            }

            $this->jppKodeByJenis[(int) $row->id] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        }

        return $this->jppKodeByJenis;
    }

    /**
     * Kode akun yang tersedia pada chart legacy `rekening_{suffix}`.
     *
     * @return array<string, true>
     */
    private function legacyChart(string $suffix): array
    {
        if (isset($this->legacyChartBySuffix[$suffix])) {
            return $this->legacyChartBySuffix[$suffix];
        }

        $this->legacyChartBySuffix[$suffix] = [];

        if (! preg_match('/^\d+$/', $suffix)) {
            return [];
        }

        $table = 'rekening_'.$suffix;
        if (! $this->legacy->tableExists($table)) {
            return [];
        }

        foreach ($this->legacy->select("SELECT kode_akun FROM `{$table}`") as $row) {
            $code = trim((string) ($row->kode_akun ?? ''));
            if ($code !== '') {
                $this->legacyChartBySuffix[$suffix][$code] = true;
            }
        }

        return $this->legacyChartBySuffix[$suffix];
    }

    /**
     * @return Generator<int, list<object>>
     */
    private function chunked(string $table, string $idCol, int $chunk, string $where = ''): Generator
    {
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $whereSql = $where !== '' ? ' WHERE '.$where : '';
        $offset = 0;
        while (true) {
            $rows = $this->legacy->select(
                "SELECT * FROM `{$table}`{$whereSql} ORDER BY `{$idCol}` ASC LIMIT ? OFFSET ?",
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
