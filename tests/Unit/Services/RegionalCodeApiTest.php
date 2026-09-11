<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RegionalCodeApi;
use Tests\TestCase;

final class RegionalCodeApiTest extends TestCase
{
    public function test_provinces_returns_data_with_fallback(): void
    {
        $api = new RegionalCodeApi;
        $provinces = $api->provinces();

        $this->assertNotEmpty($provinces);
        $this->assertArrayHasKey('code', $provinces[0]);
        $this->assertArrayHasKey('name', $provinces[0]);
    }

    public function test_regencies_returns_data_for_jawa_tengah(): void
    {
        $api = new RegionalCodeApi;
        $regencies = $api->regencies('33');

        $this->assertNotEmpty($regencies);
        $this->assertEquals('3301', $regencies[0]['code']);
    }

    public function test_districts_returns_data_for_cilacap(): void
    {
        $api = new RegionalCodeApi;
        $districts = $api->districts('3301');

        $this->assertNotEmpty($districts);
        $this->assertEquals('330101', $districts[0]['code']);
    }
}
