<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class RegionalCodeApi
{
    public function provinces(): array
    {
        return $this->get('/provinces', 2, '', fn (): array => RegionalCodeFallback::provinces());
    }

    public function regencies(string $provinceCode): array
    {
        $this->assertCode($provinceCode, 2);

        return $this->get('/regencies/'.rawurlencode($provinceCode), 4, $provinceCode, fn (): array => RegionalCodeFallback::regencies($provinceCode));
    }

    public function districts(string $regencyCode): array
    {
        $this->assertCode($regencyCode, 4);

        return $this->get('/districts/'.rawurlencode($regencyCode), 6, $regencyCode, fn (): array => RegionalCodeFallback::districts($regencyCode));
    }

    public function villages(string $districtCode): array
    {
        $this->assertCode($districtCode, 6);

        return $this->get('/villages/'.rawurlencode($districtCode), 10, $districtCode, fn (): array => [
            ['code' => $districtCode.'0001', 'name' => 'DESA/KELURAHAN 01'],
            ['code' => $districtCode.'0002', 'name' => 'DESA/KELURAHAN 02'],
        ]);
    }

    private function get(string $path, int $codeLength, string $parentCode, ?callable $fallback = null): array
    {
        $cacheKey = 'regional-code:'.md5($path);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $baseUrl = rtrim((string) config('services.regional_api.base_url', 'https://api.kodewilayah.web.id'), '/');
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(3)
                ->get($path);

            if ($response->successful()) {
                $payload = $response->json();
                $data = is_array($payload) ? ($payload['data'] ?? null) : null;

                if (is_array($data) && $data !== []) {
                    $result = array_map(function (mixed $item) use ($codeLength, $parentCode): array {
                        if (! is_array($item) || ! isset($item['code'], $item['name'])) {
                            throw new RuntimeException('Invalid region item');
                        }
                        $code = (string) $item['code'];
                        $name = trim((string) $item['name']);
                        if (strlen($code) !== $codeLength || ! ctype_digit($code) || strlen($name) < 1) {
                            throw new RuntimeException('Invalid region code or name');
                        }
                        if ($parentCode !== '' && ! str_starts_with($code, $parentCode)) {
                            throw new RuntimeException('Invalid hierarchy');
                        }

                        return ['code' => $code, 'name' => $name];
                    }, $data);

                    Cache::put($cacheKey, $result, now()->addDay());

                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Regional API request failed, using fallback dataset.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        if ($fallback !== null) {
            $fallbackData = $fallback();
            Cache::put($cacheKey, $fallbackData, now()->addHour());

            return $fallbackData;
        }

        throw new RuntimeException('Regional API request failed and no fallback available.');
    }

    private function assertCode(string $code, int $length): void
    {
        if (strlen($code) !== $length || ! ctype_digit($code)) {
            throw new RuntimeException("Regional code must contain {$length} digits.");
        }
    }
}
