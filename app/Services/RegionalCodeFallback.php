<?php

declare(strict_types=1);

namespace App\Services;

final class RegionalCodeFallback
{
    /**
     * @return list<array{code: string, name: string}>
     */
    public static function provinces(): array
    {
        return [
            ['code' => '11', 'name' => 'ACEH'],
            ['code' => '12', 'name' => 'SUMATERA UTARA'],
            ['code' => '13', 'name' => 'SUMATERA BARAT'],
            ['code' => '14', 'name' => 'RIAU'],
            ['code' => '15', 'name' => 'JAMBI'],
            ['code' => '16', 'name' => 'SUMATERA SELATAN'],
            ['code' => '17', 'name' => 'BENGKULU'],
            ['code' => '18', 'name' => 'LAMPUNG'],
            ['code' => '19', 'name' => 'KEPULAUAN BANGKA BELITUNG'],
            ['code' => '21', 'name' => 'KEPULAUAN RIAU'],
            ['code' => '31', 'name' => 'DKI JAKARTA'],
            ['code' => '32', 'name' => 'JAWA BARAT'],
            ['code' => '33', 'name' => 'JAWA TENGAH'],
            ['code' => '34', 'name' => 'DI YOGYAKARTA'],
            ['code' => '35', 'name' => 'JAWA TIMUR'],
            ['code' => '36', 'name' => 'BANTEN'],
            ['code' => '51', 'name' => 'BALI'],
            ['code' => '52', 'name' => 'NUSA TENGGARA BARAT'],
            ['code' => '53', 'name' => 'NUSA TENGGARA TIMUR'],
            ['code' => '61', 'name' => 'KALIMANTAN BARAT'],
            ['code' => '62', 'name' => 'KALIMANTAN TENGAH'],
            ['code' => '63', 'name' => 'KALIMANTAN SELATAN'],
            ['code' => '64', 'name' => 'KALIMANTAN TIMUR'],
            ['code' => '65', 'name' => 'KALIMANTAN UTARA'],
            ['code' => '71', 'name' => 'SULAWESI UTARA'],
            ['code' => '72', 'name' => 'SULAWESI TENGAH'],
            ['code' => '73', 'name' => 'SULAWESI SELATAN'],
            ['code' => '74', 'name' => 'SULAWESI TENGGARA'],
            ['code' => '75', 'name' => 'GORONTALO'],
            ['code' => '76', 'name' => 'SULAWESI BARAT'],
            ['code' => '81', 'name' => 'MALUKU'],
            ['code' => '82', 'name' => 'MALUKU UTARA'],
            ['code' => '91', 'name' => 'PAPUA'],
            ['code' => '92', 'name' => 'PAPUA BARAT'],
            ['code' => '93', 'name' => 'PAPUA SELATAN'],
            ['code' => '94', 'name' => 'PAPUA TENGAH'],
            ['code' => '95', 'name' => 'PAPUA PEGUNUNGAN'],
            ['code' => '96', 'name' => 'PAPUA BARAT DAYA'],
        ];
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function regencies(string $provinceCode): array
    {
        $all = [
            // Jawa Tengah (33)
            '33' => [
                ['code' => '3301', 'name' => 'KABUPATEN CILACAP'],
                ['code' => '3302', 'name' => 'KABUPATEN BANYUMAS'],
                ['code' => '3303', 'name' => 'KABUPATEN PURBALINGGA'],
                ['code' => '3304', 'name' => 'KABUPATEN BANJARNEGARA'],
                ['code' => '3305', 'name' => 'KABUPATEN KEBUMEN'],
                ['code' => '3306', 'name' => 'KABUPATEN PURWOREJO'],
                ['code' => '3307', 'name' => 'KABUPATEN WONOSOBO'],
                ['code' => '3308', 'name' => 'KABUPATEN MAGELANG'],
                ['code' => '3309', 'name' => 'KABUPATEN BOYOLALI'],
                ['code' => '3310', 'name' => 'KABUPATEN KLATEN'],
                ['code' => '3311', 'name' => 'KABUPATEN SUKOHARJO'],
                ['code' => '3312', 'name' => 'KABUPATEN WONOGIRI'],
                ['code' => '3313', 'name' => 'KABUPATEN KARANGANYAR'],
                ['code' => '3314', 'name' => 'KABUPATEN SRAGEN'],
                ['code' => '3315', 'name' => 'KABUPATEN GROBOGAN'],
                ['code' => '3316', 'name' => 'KABUPATEN BLORA'],
                ['code' => '3317', 'name' => 'KABUPATEN REMBANG'],
                ['code' => '3318', 'name' => 'KABUPATEN PATI'],
                ['code' => '3319', 'name' => 'KABUPATEN KUDUS'],
                ['code' => '3320', 'name' => 'KABUPATEN JEPARA'],
                ['code' => '3321', 'name' => 'KABUPATEN DEMAK'],
                ['code' => '3322', 'name' => 'KABUPATEN SEMARANG'],
                ['code' => '3323', 'name' => 'KABUPATEN TEMANGGUNG'],
                ['code' => '3324', 'name' => 'KABUPATEN KENDAL'],
                ['code' => '3325', 'name' => 'KABUPATEN BATANG'],
                ['code' => '3326', 'name' => 'KABUPATEN PEKALONGAN'],
                ['code' => '3327', 'name' => 'KABUPATEN PEMALANG'],
                ['code' => '3328', 'name' => 'KABUPATEN TEGAL'],
                ['code' => '3329', 'name' => 'KABUPATEN BREBES'],
                ['code' => '3371', 'name' => 'KOTA MAGELANG'],
                ['code' => '3372', 'name' => 'KOTA SURAKARTA'],
                ['code' => '3373', 'name' => 'KOTA SALATIGA'],
                ['code' => '3374', 'name' => 'KOTA SEMARANG'],
                ['code' => '3375', 'name' => 'KOTA PEKALONGAN'],
                ['code' => '3376', 'name' => 'KOTA TEGAL'],
            ],
            // Jawa Barat (32)
            '32' => [
                ['code' => '3201', 'name' => 'KABUPATEN BOGOR'],
                ['code' => '3202', 'name' => 'KABUPATEN SUKABUMI'],
                ['code' => '3203', 'name' => 'KABUPATEN CIANJUR'],
                ['code' => '3204', 'name' => 'KABUPATEN BANDUNG'],
                ['code' => '3205', 'name' => 'KABUPATEN GARUT'],
                ['code' => '3206', 'name' => 'KABUPATEN TASIKMALAYA'],
                ['code' => '3207', 'name' => 'KABUPATEN CIAMIS'],
                ['code' => '3208', 'name' => 'KABUPATEN KUNINGAN'],
                ['code' => '3209', 'name' => 'KABUPATEN CIREBON'],
                ['code' => '3210', 'name' => 'KABUPATEN MAJALENGKA'],
                ['code' => '3211', 'name' => 'KABUPATEN SUMEDANG'],
                ['code' => '3212', 'name' => 'KABUPATEN INDRAMAYU'],
                ['code' => '3213', 'name' => 'KABUPATEN SUBANG'],
                ['code' => '3214', 'name' => 'KABUPATEN PURWAKARTA'],
                ['code' => '3215', 'name' => 'KABUPATEN KARAWANG'],
                ['code' => '3216', 'name' => 'KABUPATEN BEKASI'],
                ['code' => '3217', 'name' => 'KABUPATEN BANDUNG BARAT'],
                ['code' => '3218', 'name' => 'KABUPATEN PANGANDARAN'],
                ['code' => '3271', 'name' => 'KOTA BOGOR'],
                ['code' => '3272', 'name' => 'KOTA SUKABUMI'],
                ['code' => '3273', 'name' => 'KOTA BANDUNG'],
                ['code' => '3274', 'name' => 'KOTA CIREBON'],
                ['code' => '3275', 'name' => 'KOTA BEKASI'],
                ['code' => '3276', 'name' => 'KOTA DEPOK'],
                ['code' => '3277', 'name' => 'KOTA CIMAHI'],
                ['code' => '3278', 'name' => 'KOTA TASIKMALAYA'],
                ['code' => '3279', 'name' => 'KOTA BANJAR'],
            ],
            // Jawa Timur (35)
            '35' => [
                ['code' => '3501', 'name' => 'KABUPATEN PACITAN'],
                ['code' => '3502', 'name' => 'KABUPATEN PONOROGO'],
                ['code' => '3503', 'name' => 'KABUPATEN TRENGGALEK'],
                ['code' => '3504', 'name' => 'KABUPATEN TULUNGAGUNG'],
                ['code' => '3505', 'name' => 'KABUPATEN BLITAR'],
                ['code' => '3506', 'name' => 'KABUPATEN KEDIRI'],
                ['code' => '3507', 'name' => 'KABUPATEN MALANG'],
                ['code' => '3508', 'name' => 'KABUPATEN LUMAJANG'],
                ['code' => '3509', 'name' => 'KABUPATEN JEMBER'],
                ['code' => '3510', 'name' => 'KABUPATEN BANYUWANGI'],
                ['code' => '3511', 'name' => 'KABUPATEN BONDOWOSO'],
                ['code' => '3512', 'name' => 'KABUPATEN SITUBONDO'],
                ['code' => '3513', 'name' => 'KABUPATEN PROBOLINGGO'],
                ['code' => '3514', 'name' => 'KABUPATEN PASURUAN'],
                ['code' => '3515', 'name' => 'KABUPATEN SIDOARJO'],
                ['code' => '3516', 'name' => 'KABUPATEN MOJOKERTO'],
                ['code' => '3517', 'name' => 'KABUPATEN JOMBANG'],
                ['code' => '3518', 'name' => 'KABUPATEN NGANJUK'],
                ['code' => '3519', 'name' => 'KABUPATEN MADIUN'],
                ['code' => '3520', 'name' => 'KABUPATEN MAGETAN'],
                ['code' => '3521', 'name' => 'KABUPATEN NGAWI'],
                ['code' => '3522', 'name' => 'KABUPATEN BOJONEGORO'],
                ['code' => '3523', 'name' => 'KABUPATEN TUBAN'],
                ['code' => '3524', 'name' => 'KABUPATEN LAMONGAN'],
                ['code' => '3525', 'name' => 'KABUPATEN GRESIK'],
                ['code' => '3526', 'name' => 'KABUPATEN BANGKALAN'],
                ['code' => '3527', 'name' => 'KABUPATEN SAMPANG'],
                ['code' => '3528', 'name' => 'KABUPATEN PAMEKASAN'],
                ['code' => '3529', 'name' => 'KABUPATEN SUMENEP'],
                ['code' => '3571', 'name' => 'KOTA KEDIRI'],
                ['code' => '3572', 'name' => 'KOTA BLITAR'],
                ['code' => '3573', 'name' => 'KOTA MALANG'],
                ['code' => '3574', 'name' => 'KOTA PROBOLINGGO'],
                ['code' => '3575', 'name' => 'KOTA PASURUAN'],
                ['code' => '3576', 'name' => 'KOTA MOJOKERTO'],
                ['code' => '3577', 'name' => 'KOTA MADIUN'],
                ['code' => '3578', 'name' => 'KOTA SURABAYA'],
                ['code' => '3579', 'name' => 'KOTA BATU'],
            ],
            // DI Yogyakarta (34)
            '34' => [
                ['code' => '3401', 'name' => 'KABUPATEN KULON PROGO'],
                ['code' => '3402', 'name' => 'KABUPATEN BANTUL'],
                ['code' => '3403', 'name' => 'KABUPATEN GUNUNGKIDUL'],
                ['code' => '3404', 'name' => 'KABUPATEN SLEMAN'],
                ['code' => '3471', 'name' => 'KOTA YOGYAKARTA'],
            ],
            // DKI Jakarta (31)
            '31' => [
                ['code' => '3101', 'name' => 'KABUPATEN KEPULAUAN SERIBU'],
                ['code' => '3171', 'name' => 'KOTA JAKARTA SELATAN'],
                ['code' => '3172', 'name' => 'KOTA JAKARTA TIMUR'],
                ['code' => '3173', 'name' => 'KOTA JAKARTA PUSAT'],
                ['code' => '3174', 'name' => 'KOTA JAKARTA BARAT'],
                ['code' => '3175', 'name' => 'KOTA JAKARTA UTARA'],
            ],
            // Banten (36)
            '36' => [
                ['code' => '3601', 'name' => 'KABUPATEN PANDEGLANG'],
                ['code' => '3602', 'name' => 'KABUPATEN LEBAK'],
                ['code' => '3603', 'name' => 'KABUPATEN TANGERANG'],
                ['code' => '3604', 'name' => 'KABUPATEN SERANG'],
                ['code' => '3671', 'name' => 'KOTA TANGERANG'],
                ['code' => '3672', 'name' => 'KOTA CILEGON'],
                ['code' => '3673', 'name' => 'KOTA SERANG'],
                ['code' => '3674', 'name' => 'KOTA TANGERANG SELATAN'],
            ],
        ];

        if (isset($all[$provinceCode])) {
            return $all[$provinceCode];
        }

        // Generic fallback regencies for other provinces
        return [
            ['code' => $provinceCode.'01', 'name' => 'KABUPATEN PUSAT '.$provinceCode],
            ['code' => $provinceCode.'02', 'name' => 'KABUPATEN UTARA '.$provinceCode],
            ['code' => $provinceCode.'71', 'name' => 'KOTA PUSAT '.$provinceCode],
        ];
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function districts(string $regencyCode): array
    {
        // Sample districts for known regencies
        $districts = [
            '3301' => [ // Cilacap
                ['code' => '330101', 'name' => 'KEDUNGREJA'],
                ['code' => '330102', 'name' => 'KESUGIHAN'],
                ['code' => '330103', 'name' => 'ADIPALA'],
                ['code' => '330104', 'name' => 'BINANGUN'],
                ['code' => '330105', 'name' => 'NUSAWUNGU'],
                ['code' => '330106', 'name' => 'KROYA'],
                ['code' => '330107', 'name' => 'MAOS'],
                ['code' => '330108', 'name' => 'JERUKLEGI'],
                ['code' => '330109', 'name' => 'KAWUNGANTEN'],
                ['code' => '330110', 'name' => 'GANDRUNGMANGU'],
                ['code' => '330111', 'name' => 'SIDAREJA'],
                ['code' => '330112', 'name' => 'KARANGPUCUNG'],
                ['code' => '330113', 'name' => 'CIMANGGU'],
                ['code' => '330114', 'name' => 'MAJENANG'],
                ['code' => '330115', 'name' => 'WANAREJA'],
                ['code' => '330116', 'name' => 'DAYEUHLUHUR'],
                ['code' => '330117', 'name' => 'SAMPANG'],
                ['code' => '330118', 'name' => 'CIPARI'],
                ['code' => '330119', 'name' => 'PATIMUAN'],
                ['code' => '330120', 'name' => 'BANTARSARI'],
                ['code' => '330121', 'name' => 'CILACAP SELATAN'],
                ['code' => '330122', 'name' => 'CILACAP TENGAH'],
                ['code' => '330123', 'name' => 'CILACAP UTARA'],
                ['code' => '330124', 'name' => 'KAMPUNG LAUT'],
            ],
            '3302' => [ // Banyumas
                ['code' => '330201', 'name' => 'LUMBIR'],
                ['code' => '330202', 'name' => 'WANGON'],
                ['code' => '330203', 'name' => 'JATILAWANG'],
                ['code' => '330204', 'name' => 'RAWALO'],
                ['code' => '330205', 'name' => 'KEBASEN'],
                ['code' => '330206', 'name' => 'KEMRANJEN'],
                ['code' => '330207', 'name' => 'SUMPIUH'],
                ['code' => '330208', 'name' => 'TAMBAK'],
                ['code' => '330209', 'name' => 'SOMAGEDE'],
                ['code' => '330210', 'name' => 'KALIBAGOR'],
                ['code' => '330211', 'name' => 'BANYUMAS'],
                ['code' => '330212', 'name' => 'PATIKRAJA'],
                ['code' => '330213', 'name' => 'PURWOJATI'],
                ['code' => '330214', 'name' => 'AJIBARANG'],
                ['code' => '330215', 'name' => 'GUMELAR'],
                ['code' => '330216', 'name' => 'PEKUNCEN'],
                ['code' => '330217', 'name' => 'CILONGOK'],
                ['code' => '330218', 'name' => 'KARANGLEWAS'],
                ['code' => '330219', 'name' => 'SOKARAJA'],
                ['code' => '330220', 'name' => 'KEMBARAN'],
                ['code' => '330221', 'name' => 'SUMBANG'],
                ['code' => '330222', 'name' => 'BATURRADEN'],
                ['code' => '330223', 'name' => 'KEDUNGBANTENG'],
                ['code' => '330224', 'name' => 'PURWOKERTO SELATAN'],
                ['code' => '330225', 'name' => 'PURWOKERTO BARAT'],
                ['code' => '330226', 'name' => 'PURWOKERTO TIMUR'],
                ['code' => '330227', 'name' => 'PURWOKERTO UTARA'],
            ],
            '3201' => [ // Bogor
                ['code' => '320101', 'name' => 'CIBINONG'],
                ['code' => '320102', 'name' => 'GUNUNG PUTRI'],
                ['code' => '320103', 'name' => 'CITEUREUP'],
                ['code' => '320104', 'name' => 'SUKARAJA'],
                ['code' => '320105', 'name' => 'BABAKAN MADANG'],
                ['code' => '320106', 'name' => 'JONGGOL'],
                ['code' => '320107', 'name' => 'CILEUNGSI'],
                ['code' => '320108', 'name' => 'CARIU'],
                ['code' => '320109', 'name' => 'TANJUNGSARI'],
            ],
        ];

        if (isset($districts[$regencyCode])) {
            return $districts[$regencyCode];
        }

        // Generic fallback districts (01..05) for any regency code
        $res = [];
        for ($i = 1; $i <= 5; $i++) {
            $code = $regencyCode.sprintf('%02d', $i);
            $res[] = ['code' => $code, 'name' => 'KECAMATAN '.$i.' ('.$regencyCode.')'];
        }

        return $res;
    }
}
