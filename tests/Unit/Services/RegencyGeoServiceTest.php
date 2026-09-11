<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Accounting\Services\Reports\RegencyConsolidatedReportService;
use App\Services\RegencyGeoService;
use PHPUnit\Framework\TestCase;

final class RegencyGeoServiceTest extends TestCase
{
    public function test_resolves_known_regency_center(): void
    {
        $cilacap = RegencyGeoService::resolveRegencyCenter('3301');
        $this->assertIsArray($cilacap);
        $this->assertArrayHasKey('lat', $cilacap);
        $this->assertArrayHasKey('lng', $cilacap);
        $this->assertEqualsWithDelta(-7.5350, $cilacap['lat'], 0.01);
        $this->assertEqualsWithDelta(108.9850, $cilacap['lng'], 0.01);
    }

    public function test_resolves_known_district_coordinates(): void
    {
        $kedungreja = RegencyGeoService::resolveDistrictCoordinate('330101', '3301');
        $this->assertIsArray($kedungreja);
        $this->assertEqualsWithDelta(-7.5850, $kedungreja['lat'], 0.001);
        $this->assertEqualsWithDelta(108.7980, $kedungreja['lng'], 0.001);
    }

    public function test_fallback_district_offset_distribution(): void
    {
        $dist1 = RegencyGeoService::resolveDistrictCoordinate('999901', '9999', 0, 5);
        $dist2 = RegencyGeoService::resolveDistrictCoordinate('999902', '9999', 1, 5);

        $this->assertNotEquals($dist1, $dist2);
        $this->assertIsFloat($dist1['lat']);
        $this->assertIsFloat($dist1['lng']);
    }

    public function test_evaluates_npl_levels(): void
    {
        $sehat = RegencyConsolidatedReportService::evaluateNpl(3.5);
        $this->assertSame('Sehat (≤ 5%)', $sehat['status']);
        $this->assertSame('success', $sehat['tone']);

        $cukup = RegencyConsolidatedReportService::evaluateNpl(8.0);
        $this->assertSame('Cukup Sehat (5–10%)', $cukup['status']);
        $this->assertSame('primary', $cukup['tone']);

        $kurang = RegencyConsolidatedReportService::evaluateNpl(18.2);
        $this->assertSame('Kurang Sehat (10–25%)', $kurang['status']);
        $this->assertSame('warning', $kurang['tone']);

        $tidakSehat = RegencyConsolidatedReportService::evaluateNpl(32.0);
        $this->assertSame('Tidak Sehat (> 25%)', $tidakSehat['status']);
        $this->assertSame('error', $tidakSehat['tone']);
    }

    public function test_resolves_saved_coordinate_with_zoom(): void
    {
        $geo = RegencyGeoService::resolveSavedCoordinate('-7.5350', '108.9850', 12);

        $this->assertNotNull($geo);
        $this->assertSame(-7.5350, $geo['lat']);
        $this->assertSame(108.9850, $geo['lng']);
        $this->assertSame(12, $geo['zoom']);
    }
}
