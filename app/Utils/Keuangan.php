<?php

namespace App\Utils;

/**
 * Compatibility port of helper numerik `siupk/app/Utils/Keuangan.php`.
 *
 * Hanya memuat method yang dipakai oleh template dokumen pinjaman individu:
 * `terbilang`, `pembulatan`, `startWith`, dan `romawi`.
 */
class Keuangan
{
    public function __construct(private string $defaultRounding = '5000') {}

    public function setDefaultRounding(string $rounding): void
    {
        $this->defaultRounding = $rounding;
    }

    public static function bulatkan($angka)
    {
        $angka = round((float) $angka);
        $pembulatan = 1000;
        $ratusan = substr((string) $angka, -3);
        $nilai_tengah = $pembulatan / 2;

        if ((float) $ratusan < $nilai_tengah) {
            $akhir = $angka - (float) $ratusan;
        } else {
            $akhir = $angka + ($pembulatan - (float) $ratusan);
        }

        return $akhir;
    }

    public function pembulatan($angka, $pembulatan = null, $dump = false)
    {
        $angka = round((float) $angka);

        if ($pembulatan === null || $pembulatan === '') {
            $pembulatan = $this->defaultRounding;
        }

        $pembulatan = (string) $pembulatan;
        $sistem = 'auto';

        if (self::startWith($pembulatan, '+')) {
            $sistem = 'keatas';
            $pembulatan = (string) intval($pembulatan);
        }

        if (self::startWith($pembulatan, '-')) {
            $sistem = 'kebawah';
            $pembulatan = (string) intval(((float) $pembulatan) * -1);
        }

        $nilaiPembulatan = (float) $pembulatan;
        if ($nilaiPembulatan <= 0) {
            return $angka;
        }

        $ratusan = (float) substr((string) $angka, -strlen((string) ($nilaiPembulatan / 2)));
        $nilai_tengah = $nilaiPembulatan / 2;

        if ($sistem === 'keatas') {
            $akhir = $angka + ($nilaiPembulatan - $ratusan);
        } elseif ($sistem === 'kebawah') {
            $akhir = $angka - $ratusan;
        } else {
            if ($ratusan <= $nilai_tengah) {
                $akhir = $angka - $ratusan;
            } else {
                $akhir = $angka + ($nilaiPembulatan - $ratusan);
            }
        }

        return $akhir;
    }

    public static function startWith($string, $startString)
    {
        $string = (string) $string;
        $len = strlen($startString);

        return substr($string, 0, $len) === $startString;
    }

    public function penyebut($nilai)
    {
        $nilai = abs((float) $nilai);
        $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $temp = '';

        if ($nilai < 12) {
            $temp = ' '.$huruf[(int) $nilai];
        } elseif ($nilai < 20) {
            $temp = $this->penyebut($nilai - 10).' belas';
        } elseif ($nilai < 100) {
            $temp = $this->penyebut($nilai / 10).' puluh'.$this->penyebut($nilai % 10);
        } elseif ($nilai < 200) {
            $temp = ' seratus'.$this->penyebut($nilai - 100);
        } elseif ($nilai < 1000) {
            $temp = $this->penyebut($nilai / 100).' ratus'.$this->penyebut($nilai % 100);
        } elseif ($nilai < 2000) {
            $temp = ' seribu'.$this->penyebut($nilai - 1000);
        } elseif ($nilai < 1000000) {
            $temp = $this->penyebut($nilai / 1000).' ribu'.$this->penyebut($nilai % 1000);
        } elseif ($nilai < 1000000000) {
            $temp = $this->penyebut($nilai / 1000000).' juta'.$this->penyebut($nilai % 1000000);
        } elseif ($nilai < 1000000000000) {
            $temp = $this->penyebut($nilai / 1000000000).' milyar'.$this->penyebut(fmod($nilai, 1000000000));
        } elseif ($nilai < 1000000000000000) {
            $temp = $this->penyebut($nilai / 1000000000000).' trilyun'.$this->penyebut(fmod($nilai, 1000000000000));
        }

        return $temp;
    }

    public function terbilang($nilai)
    {
        if ($nilai < 0) {
            $hasil = 'minus '.trim($this->penyebut($nilai));
        } else {
            $hasil = trim($this->penyebut($nilai));
        }

        return ucwords($hasil);
    }

    public function romawi(int $angka)
    {
        if ($angka < 1) {
            return '';
        }

        $result = '';
        $lookup = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];

        foreach ($lookup as $roman => $value) {
            while ($angka >= $value) {
                $result .= $roman;
                $angka -= $value;
            }
        }

        return $result;
    }
}
