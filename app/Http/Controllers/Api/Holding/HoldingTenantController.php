<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Holding;

use App\Models\Platform\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HoldingTenantController
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()
            ->with(['placement.shard'])
            ->whereIn('status', ['active', 'read_only']);

        if ($request->filled('province_code')) {
            $query->where('province_code', (string) $request->query('province_code'));
        }

        if ($request->filled('regency_code')) {
            $query->where('regency_code', (string) $request->query('regency_code'));
        }

        if ($request->filled('district_code')) {
            $query->where('district_code', (string) $request->query('district_code'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('regency_name', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'total' => $tenants->count(),
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $tenants->map(fn (Tenant $tenant) => [
                'id' => (int) $tenant->row_id,
                'code' => (string) $tenant->code,
                'name' => (string) $tenant->name,
                'status' => (string) $tenant->status,
                'district_code' => $tenant->district_code,
                'regency_code' => $tenant->regency_code,
                'regency_name' => $tenant->regency_name,
                'province_code' => $tenant->province_code,
                'shard' => $tenant->placement?->shard?->code,
                'created_at' => $tenant->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(string|int $tenant): JsonResponse
    {
        $query = Tenant::query()->with(['placement.shard']);

        $item = is_numeric($tenant)
            ? $query->where('row_id', (int) $tenant)->first()
            : $query->where('code', (string) $tenant)->first();

        if ($item === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Holding subsidiary / tenant not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => (int) $item->row_id,
                'code' => (string) $item->code,
                'name' => (string) $item->name,
                'status' => (string) $item->status,
                'district_code' => $item->district_code,
                'regency_code' => $item->regency_code,
                'regency_name' => $item->regency_name,
                'province_code' => $item->province_code,
                'metadata' => $item->metadata,
                'shard' => $item->placement?->shard?->code,
                'created_at' => $item->created_at?->toIso8601String(),
            ],
        ]);
    }
}
