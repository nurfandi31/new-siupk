<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Validation\Validator;

final class RegencyGeoService
{
    /**
     * Known Regency Centers (lat, lng, default_zoom)
     *
     * @var array<string, array{lat: float, lng: float, zoom: int}>
     */
    private static array $regencyCoordinates = [
        // Jawa Tengah (33)
        '3301' => ['lat' => -7.5350, 'lng' => 108.9850, 'zoom' => 11],
        '3302' => ['lat' => -7.4520, 'lng' => 109.1850, 'zoom' => 11],
        '3303' => ['lat' => -7.3890, 'lng' => 109.3620, 'zoom' => 11],
        '3304' => ['lat' => -7.3970, 'lng' => 109.6970, 'zoom' => 11],
        '3305' => ['lat' => -7.6710, 'lng' => 109.6580, 'zoom' => 11],
        '3306' => ['lat' => -7.7140, 'lng' => 109.9980, 'zoom' => 11],
        '3307' => ['lat' => -7.3620, 'lng' => 109.9010, 'zoom' => 11],
        '3308' => ['lat' => -7.5580, 'lng' => 110.2210, 'zoom' => 11],
        '3309' => ['lat' => -7.5320, 'lng' => 110.5960, 'zoom' => 11],
        '3310' => ['lat' => -7.7050, 'lng' => 110.6030, 'zoom' => 11],
        '3311' => ['lat' => -7.6830, 'lng' => 110.8380, 'zoom' => 11],
        '3312' => ['lat' => -7.8180, 'lng' => 111.0250, 'zoom' => 11],
        '3313' => ['lat' => -7.5960, 'lng' => 111.0450, 'zoom' => 11],
        '3314' => ['lat' => -7.4270, 'lng' => 111.0220, 'zoom' => 11],
        '3315' => ['lat' => -7.0980, 'lng' => 110.9160, 'zoom' => 11],
        '3316' => ['lat' => -7.1520, 'lng' => 111.4170, 'zoom' => 11],
        '3317' => ['lat' => -6.7090, 'lng' => 111.3410, 'zoom' => 11],
        '3318' => ['lat' => -6.7550, 'lng' => 111.0380, 'zoom' => 11],
        '3319' => ['lat' => -6.8050, 'lng' => 110.8420, 'zoom' => 11],
        '3320' => ['lat' => -6.5890, 'lng' => 110.6690, 'zoom' => 11],
        '3321' => ['lat' => -6.8940, 'lng' => 110.6380, 'zoom' => 11],
        '3322' => ['lat' => -7.2140, 'lng' => 110.4350, 'zoom' => 11],
        '3323' => ['lat' => -7.3180, 'lng' => 110.1740, 'zoom' => 11],
        '3324' => ['lat' => -7.0250, 'lng' => 110.2030, 'zoom' => 11],
        '3325' => ['lat' => -7.0370, 'lng' => 109.8330, 'zoom' => 11],
        '3326' => ['lat' => -7.0420, 'lng' => 109.6280, 'zoom' => 11],
        '3327' => ['lat' => -7.0140, 'lng' => 109.3800, 'zoom' => 11],
        '3328' => ['lat' => -7.0280, 'lng' => 109.1380, 'zoom' => 11],
        '3329' => ['lat' => -6.9740, 'lng' => 108.9240, 'zoom' => 11],
        '3371' => ['lat' => -7.4705, 'lng' => 110.2178, 'zoom' => 12],
        '3372' => ['lat' => -7.5666, 'lng' => 110.8290, 'zoom' => 12],
        '3373' => ['lat' => -7.3305, 'lng' => 110.5084, 'zoom' => 12],
        '3374' => ['lat' => -6.9932, 'lng' => 110.4203, 'zoom' => 12],
        '3375' => ['lat' => -6.8886, 'lng' => 109.6753, 'zoom' => 12],
        '3376' => ['lat' => -6.8797, 'lng' => 109.1256, 'zoom' => 12],

        // Jawa Barat (32)
        '3201' => ['lat' => -6.5950, 'lng' => 106.8160, 'zoom' => 11],
        '3202' => ['lat' => -6.9277, 'lng' => 106.9299, 'zoom' => 11],
        '3203' => ['lat' => -6.8222, 'lng' => 107.1394, 'zoom' => 11],
        '3204' => ['lat' => -7.0253, 'lng' => 107.5198, 'zoom' => 11],
        '3205' => ['lat' => -7.2278, 'lng' => 107.9087, 'zoom' => 11],
        '3206' => ['lat' => -7.3587, 'lng' => 108.1107, 'zoom' => 11],
        '3207' => ['lat' => -7.3275, 'lng' => 108.3550, 'zoom' => 11],
        '3208' => ['lat' => -6.9760, 'lng' => 108.4830, 'zoom' => 11],
        '3209' => ['lat' => -6.7630, 'lng' => 108.5390, 'zoom' => 11],
        '3210' => ['lat' => -6.8360, 'lng' => 108.2270, 'zoom' => 11],
        '3211' => ['lat' => -6.8580, 'lng' => 107.9260, 'zoom' => 11],
        '3212' => ['lat' => -6.3260, 'lng' => 108.3220, 'zoom' => 11],
        '3213' => ['lat' => -6.5710, 'lng' => 107.7600, 'zoom' => 11],
        '3214' => ['lat' => -6.5380, 'lng' => 107.4430, 'zoom' => 11],
        '3215' => ['lat' => -6.3050, 'lng' => 107.3010, 'zoom' => 11],
        '3216' => ['lat' => -6.2380, 'lng' => 107.1530, 'zoom' => 11],
        '3217' => ['lat' => -6.8660, 'lng' => 107.4930, 'zoom' => 11],
        '3218' => ['lat' => -7.6830, 'lng' => 108.5330, 'zoom' => 11],
        '3271' => ['lat' => -6.5971, 'lng' => 106.8060, 'zoom' => 12],
        '3273' => ['lat' => -6.9175, 'lng' => 107.6191, 'zoom' => 12],

        // Jawa Timur (35)
        '3501' => ['lat' => -8.2040, 'lng' => 111.0920, 'zoom' => 11],
        '3502' => ['lat' => -7.8680, 'lng' => 111.4620, 'zoom' => 11],
        '3507' => ['lat' => -8.1660, 'lng' => 112.6310, 'zoom' => 11],
        '3515' => ['lat' => -7.4470, 'lng' => 112.7180, 'zoom' => 11],
        '3525' => ['lat' => -7.1560, 'lng' => 112.6550, 'zoom' => 11],
        '3578' => ['lat' => -7.2575, 'lng' => 112.7521, 'zoom' => 12],

        // DI Yogyakarta (34)
        '3401' => ['lat' => -7.7950, 'lng' => 110.1580, 'zoom' => 11],
        '3402' => ['lat' => -7.8930, 'lng' => 110.3540, 'zoom' => 11],
        '3403' => ['lat' => -7.9620, 'lng' => 110.6030, 'zoom' => 11],
        '3404' => ['lat' => -7.7150, 'lng' => 110.3550, 'zoom' => 11],
        '3471' => ['lat' => -7.7956, 'lng' => 110.3695, 'zoom' => 12],
    ];

    /**
     * Known District Centers (lat, lng)
     *
     * @var array<string, array{lat: float, lng: float}>
     */
    private static array $districtCoordinates = [
        // Cilacap (3301)
        '330101' => ['lat' => -7.5850, 'lng' => 108.7980],
        '330102' => ['lat' => -7.6160, 'lng' => 109.1120],
        '330103' => ['lat' => -7.6710, 'lng' => 109.1550],
        '330104' => ['lat' => -7.6320, 'lng' => 109.2890],
        '330105' => ['lat' => -7.6580, 'lng' => 109.3510],
        '330106' => ['lat' => -7.6250, 'lng' => 109.2450],
        '330107' => ['lat' => -7.6080, 'lng' => 109.1650],
        '330108' => ['lat' => -7.5680, 'lng' => 109.0230],
        '330109' => ['lat' => -7.5910, 'lng' => 108.9180],
        '330110' => ['lat' => -7.5320, 'lng' => 108.8520],
        '330111' => ['lat' => -7.4830, 'lng' => 108.8020],
        '330112' => ['lat' => -7.4120, 'lng' => 108.8950],
        '330113' => ['lat' => -7.3510, 'lng' => 108.8350],
        '330114' => ['lat' => -7.3020, 'lng' => 108.7610],
        '330115' => ['lat' => -7.3250, 'lng' => 108.6850],
        '330116' => ['lat' => -7.2410, 'lng' => 108.6080],
        '330117' => ['lat' => -7.5850, 'lng' => 109.2150],
        '330118' => ['lat' => -7.4280, 'lng' => 108.7650],
        '330119' => ['lat' => -7.6320, 'lng' => 108.7620],
        '330120' => ['lat' => -7.5450, 'lng' => 108.8910],
        '330121' => ['lat' => -7.7420, 'lng' => 109.0150],
        '330122' => ['lat' => -7.7120, 'lng' => 109.0120],
        '330123' => ['lat' => -7.6750, 'lng' => 109.0350],
        '330124' => ['lat' => -7.6880, 'lng' => 108.8750],

        // Banyumas (3302)
        '330201' => ['lat' => -7.5180, 'lng' => 108.9750],
        '330202' => ['lat' => -7.5120, 'lng' => 109.0520],
        '330203' => ['lat' => -7.5350, 'lng' => 109.1120],
        '330204' => ['lat' => -7.5280, 'lng' => 109.1850],
        '330205' => ['lat' => -7.5380, 'lng' => 109.2350],
        '330206' => ['lat' => -7.5750, 'lng' => 109.3120],
        '330207' => ['lat' => -7.6080, 'lng' => 109.3620],
        '330208' => ['lat' => -7.6180, 'lng' => 109.4050],
        '330209' => ['lat' => -7.5520, 'lng' => 109.3250],
        '330210' => ['lat' => -7.4950, 'lng' => 109.2850],
        '330211' => ['lat' => -7.5150, 'lng' => 109.2980],
        '330212' => ['lat' => -7.4820, 'lng' => 109.2150],
        '330213' => ['lat' => -7.4980, 'lng' => 109.1350],
        '330214' => ['lat' => -7.4080, 'lng' => 109.0750],
        '330215' => ['lat' => -7.3850, 'lng' => 108.9850],
        '330216' => ['lat' => -7.3420, 'lng' => 109.0850],
        '330217' => ['lat' => -7.3950, 'lng' => 109.1450],
        '330218' => ['lat' => -7.4120, 'lng' => 109.1980],
        '330219' => ['lat' => -7.4580, 'lng' => 109.2850],
        '330220' => ['lat' => -7.4180, 'lng' => 109.2850],
        '330221' => ['lat' => -7.3620, 'lng' => 109.2750],
        '330222' => ['lat' => -7.3250, 'lng' => 109.2250],
        '330223' => ['lat' => -7.3750, 'lng' => 109.2050],
        '330224' => ['lat' => -7.4420, 'lng' => 109.2350],
        '330225' => ['lat' => -7.4220, 'lng' => 109.2180],
        '330226' => ['lat' => -7.4280, 'lng' => 109.2480],
        '330227' => ['lat' => -7.4050, 'lng' => 109.2380],
    ];

    /**
     * @return array{lat: float, lng: float, zoom: int}
     */
    public static function resolveRegencyCenter(?string $regencyCode): array
    {
        $code = trim((string) $regencyCode);
        if ($code !== '' && isset(self::$regencyCoordinates[$code])) {
            return self::$regencyCoordinates[$code];
        }

        return ['lat' => -7.5000, 'lng' => 109.5000, 'zoom' => 10];
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public static function resolveDistrictCoordinate(?string $districtCode, ?string $regencyCode = null, int $index = 0, int $total = 1): array
    {
        $districtCode = trim((string) $districtCode);
        if ($districtCode !== '' && isset(self::$districtCoordinates[$districtCode])) {
            return self::$districtCoordinates[$districtCode];
        }

        $regencyCode = $regencyCode ?: (strlen($districtCode) >= 4 ? substr($districtCode, 0, 4) : '3301');
        $center = self::resolveRegencyCenter($regencyCode);

        $angle = ($index / max(1, $total)) * 2 * M_PI;
        $radius = 0.05 + (($index % 3) * 0.035);

        return [
            'lat' => round($center['lat'] + (sin($angle) * $radius), 6),
            'lng' => round($center['lng'] + (cos($angle) * ($radius * 1.15)), 6),
        ];
    }

    /**
     * @return array{lat: float, lng: float, zoom: int}|null
     */
    public static function resolveSavedCoordinate(float|string|null $latitude, float|string|null $longitude, ?int $zoom = null): ?array
    {
        if ($latitude === null || $longitude === null || trim((string) $latitude) === '' || trim((string) $longitude) === '') {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng, 'zoom' => max(3, min(19, (int) ($zoom ?? 13)))];
    }

    public static function validateWithinRegency(
        ?string $regencyCode,
        float|string|null $latitude,
        float|string|null $longitude,
        Validator $validator,
    ): void {
        $saved = self::resolveSavedCoordinate($latitude, $longitude);
        if ($saved === null) {
            return;
        }

        $center = self::resolveRegencyCenter($regencyCode);
        $maxDelta = 0.45;

        if (abs($saved['lat'] - $center['lat']) > $maxDelta || abs($saved['lng'] - $center['lng']) > ($maxDelta * 1.1)) {
            $validator->errors()->add(
                'map_latitude',
                'Koordinat harus berada di dalam batas kabupaten/kota terpilih.',
            );
        }
    }
}
