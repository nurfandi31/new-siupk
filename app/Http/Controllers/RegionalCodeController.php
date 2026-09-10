<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RegencyGeoService;
use App\Services\RegionalCodeApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RegionalCodeController
{
    public function provinces(RegionalCodeApi $regional): JsonResponse
    {
        return response()->json(['data' => $regional->provinces()]);
    }

    public function regencies(Request $request, RegionalCodeApi $regional): JsonResponse
    {
        return response()->json(['data' => $regional->regencies((string) $request->route('province'))]);
    }

    public function districts(Request $request, RegionalCodeApi $regional): JsonResponse
    {
        return response()->json(['data' => $regional->districts((string) $request->route('regency'))]);
    }

    public function villages(Request $request, RegionalCodeApi $regional): JsonResponse
    {
        return response()->json(['data' => $regional->villages((string) $request->route('district'))]);
    }

    public function regencyCenter(Request $request): JsonResponse
    {
        return response()->json([
            'data' => RegencyGeoService::resolveRegencyCenter(
                (string) $request->route('regency'),
            ),
        ]);
    }
}
