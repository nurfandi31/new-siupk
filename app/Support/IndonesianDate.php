<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Format tanggal dalam gaya Indonesia untuk dokumen PDF pinjaman.
 */
final class IndonesianDate
{
    public const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /** Hari dalam bahasa Indonesia, e.g. "Senin". */
    public static function dayName(string $ymd): string
    {
        return self::resolve($ymd, static fn (CarbonImmutable $d): string => $d->locale('id')->translatedFormat('l'));
    }

    /** Tanggal dengan bulan Romawi, e.g. "12/VII/2026". */
    public static function roman(string $ymd): string
    {
        return self::resolve($ymd, static function (CarbonImmutable $d): string {
            return $d->format('d').'/'.self::ROMAN_MONTHS[(int) $d->format('n')].'/'.$d->format('Y');
        });
    }

    /** Format Latin, e.g. "12 Juli 2026". */
    public static function latin(string $ymd): string
    {
        return self::resolve($ymd, static fn (CarbonImmutable $d): string => $d->locale('id')->translatedFormat('d F Y'));
    }

    /** Format Indo singkat, e.g. "12 Jul 2026". */
    public static function short(string $ymd): string
    {
        return self::resolve($ymd, static fn (CarbonImmutable $d): string => $d->locale('id')->translatedFormat('d M Y'));
    }

    /** Hanya angka hari, e.g. "12". */
    public static function day(string $ymd): string
    {
        return self::resolve($ymd, static fn (CarbonImmutable $d): string => $d->format('d'));
    }

    /** Nama bulan Indonesia, e.g. "Juli". */
    public static function monthName(string $ymd): string
    {
        return self::resolve($ymd, static fn (CarbonImmutable $d): string => $d->locale('id')->translatedFormat('F'));
    }

    /** Tahun 4 digit, e.g. "2026". */
    public static function year(string $ymd): string
    {
        return self::resolve($ymd, static fn (CarbonImmutable $d): string => $d->format('Y'));
    }

    private static function resolve(string $ymd, callable $cb): string
    {
        if ($ymd === '') {
            return '';
        }
        try {
            return $cb(CarbonImmutable::parse($ymd));
        } catch (\Throwable) {
            return $ymd;
        }
    }
}
