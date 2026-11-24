<?php

declare(strict_types=1);

namespace App\Domain\Lending\Support;

/**
 * Peta kode produk legacy (`jenis_produk_pinjaman.kode`).
 *
 * Kode ini menentukan akun pacuan:
 *   1.1.03.{kode}  piutang pokok
 *   4.1.01.{kode}  pendapatan jasa
 *   4.1.02.{kode}  pendapatan denda
 *
 * Nilai `jenis_pp` adalah id pada tabel global `jenis_produk_pinjaman`.
 * Id 4/6/7/8/10/16 semuanya memakai kode 4, id 9/12 kode 5, id 11 kode 6,
 * id 13/14 kode 7, dan id 15 kode 10.
 */
final class LegacyProductKode
{
    /** @var array<int, int> */
    public const BY_JENIS_PP = [
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        6 => 4,
        7 => 4,
        8 => 4,
        9 => 5,
        10 => 4,
        11 => 6,
        12 => 5,
        13 => 7,
        14 => 7,
        15 => 10,
        16 => 4,
    ];

    public static function normalize(mixed $kode): ?string
    {
        $value = trim((string) $kode);
        if ($value === '' || (int) $value <= 0) {
            return null;
        }

        return str_pad((string) (int) $value, 2, '0', STR_PAD_LEFT);
    }

    public static function fromJenisPp(mixed $jenisPp): ?string
    {
        $id = (int) $jenisPp;
        if ($id <= 0 || ! isset(self::BY_JENIS_PP[$id])) {
            return null;
        }

        return self::normalize(self::BY_JENIS_PP[$id]);
    }
}
