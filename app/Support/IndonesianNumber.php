<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Format angka dan nominal dalam gaya Indonesia untuk dokumen PDF pinjaman.
 *
 * Mirip dengan helper legacy `App\Utils\Keuangan` di `siupk`, di-port ke
 * arsitektur modern.
 */
final class IndonesianNumber
{
    /**
     * Mengkonversi angka menjadi ucapan bahasa Indonesia, e.g. 1500000 â†’
     * "satu juta lima ratus ribu rupiah".
     */
    public static function spelledOut(float $number): string
    {
        $number = (int) round($number);
        if ($number === 0) {
            return 'nol';
        }

        $units = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        $words = self::toWords(abs($number), $units);

        return trim($words).' rupiah';
    }

    /** Bulatkan nilai ke ribuan (untuk kewajiban angsuran). */
    public static function roundInstallment(float $value): float
    {
        return round($value / 1000) * 1000;
    }

    private static function toWords(int $value, array $units): string
    {
        if ($value < 12) {
            return $units[$value];
        }
        if ($value < 20) {
            return $units[$value - 10].' belas';
        }
        if ($value < 100) {
            $tens = (int) ($value / 10);
            $rest = $value % 10;

            return $units[$tens].' puluh'.($rest > 0 ? ' '.self::toWords($rest, $units) : '');
        }
        if ($value < 200) {
            return 'seratus'.($value > 100 ? ' '.self::toWords($value - 100, $units) : '');
        }
        if ($value < 1000) {
            $hundreds = (int) ($value / 100);
            $rest = $value % 100;

            return $units[$hundreds].' ratus'.($rest > 0 ? ' '.self::toWords($rest, $units) : '');
        }
        if ($value < 2000) {
            return 'seribu'.($value > 1000 ? ' '.self::toWords($value - 1000, $units) : '');
        }
        if ($value < 1000000) {
            $thousands = (int) ($value / 1000);
            $rest = $value % 1000;

            return self::toWords($thousands, $units).' ribu'.($rest > 0 ? ' '.self::toWords($rest, $units) : '');
        }
        if ($value < 1000000000) {
            $millions = (int) ($value / 1000000);
            $rest = $value % 1000000;

            return self::toWords($millions, $units).' juta'.($rest > 0 ? ' '.self::toWords($rest, $units) : '');
        }
        if ($value < 1000000000000) {
            $billions = (int) ($value / 1000000000);
            $rest = $value % 1000000000;

            return self::toWords($billions, $units).' miliar'.($rest > 0 ? ' '.self::toWords($rest, $units) : '');
        }

        $trillions = (int) ($value / 1000000000000);
        $rest = $value % 1000000000000;

        return self::toWords($trillions, $units).' triliun'.($rest > 0 ? ' '.self::toWords($rest, $units) : '');
    }
}
