<?php

namespace App\Utils;

use Carbon\Carbon;

/**
 * Compatibility port of `siupk/app/Utils/Tanggal.php`.
 *
 * Dipertahankan apa adanya agar template dokumen legacy (perguliran_i/dokumen)
 * dapat dirender tanpa perubahan perilaku.
 */
class Tanggal
{
    public static function tglIndo($tanggal, $format = 'DD/MM/YYYY')
    {
        if ($tanggal != '') {
            $array_tgl = explode('-', (string) $tanggal);
            $tahun = $array_tgl[0] ?? '';
            $bulan = $array_tgl[1] ?? '';
            $hari = $array_tgl[2] ?? '';

            if (strlen($hari) > 0 && strlen($bulan) > 0) {
                $tanggal = $tahun.'-'.$bulan.'-'.$hari;
            } elseif (strlen($bulan) > 0) {
                $tanggal = $tahun.'-'.$bulan.'-01';
            } else {
                $tanggal = $tahun.'-12-31';
            }

            $tgl = new Carbon($tanggal);

            return $tgl->isoFormat($format);
        }

        return date('d/m/Y');
    }

    public static function tglNasional($tanggal)
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tanggal)) {
            return $tanggal;
        }

        return Carbon::createFromFormat('d/m/Y', (string) $tanggal)->format('Y-m-d');
    }

    public static function tglRomawi($tanggal)
    {
        $keuangan = new Keuangan;
        $array_tgl = explode('-', (string) $tanggal);
        $tahun = $array_tgl[0] ?? '';
        $bulan = $array_tgl[1] ?? '';
        $hari = $array_tgl[2] ?? '';

        $bulan_rom = $keuangan->romawi((int) $bulan);
        $hari_rom = $keuangan->romawi((int) $hari);

        return $bulan_rom.'/'.$tahun;
    }

    public static function tglLatin($tanggal)
    {
        $tgl = explode('-', (string) $tanggal);

        return ($tgl[2] ?? '').' '.self::namaBulan($tanggal).' '.($tgl[0] ?? '');
    }

    public static function tglOjk($tanggal)
    {
        if (empty($tanggal)) {
            return date('d-m-Y');
        }
        $parts = explode('-', (string) $tanggal);
        if (count($parts) === 3 && strlen($parts[0]) === 4) {
            return $parts[2].'-'.$parts[1].'-'.$parts[0];
        }

        return self::tglIndo($tanggal, 'DD-MM-YYYY');
    }

    public static function tahun($tanggal)
    {
        return explode('-', (string) $tanggal)[0] ?? '';
    }

    public static function bulan($tanggal)
    {
        return explode('-', (string) $tanggal)[1] ?? '';
    }

    public static function namaBulan($tanggal)
    {
        $bln = self::bulan($tanggal);

        return match ($bln) {
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
            default => '',
        };
    }

    public static function hari($tanggal)
    {
        return explode('-', (string) $tanggal)[2] ?? '';
    }

    public static function namaHari($tanggal)
    {
        if (empty($tanggal)) {
            return 'Tidak di ketahui';
        }

        $hari = date('D', strtotime((string) $tanggal));

        return match ($hari) {
            'Sun' => 'Minggu',
            'Mon' => 'Senin',
            'Tue' => 'Selasa',
            'Wed' => 'Rabu',
            'Thu' => 'Kamis',
            'Fri' => 'Jumat',
            'Sat' => 'Sabtu',
            default => 'Tidak di ketahui',
        };
    }
}
