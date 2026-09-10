<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\Tenant;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\TenantImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class TenantImpersonationController
{
    public function impersonate(Request $request, Tenant $tenant, TenantImpersonationService $service, AuditLogger $audit): JsonResponse|Response
    {
        $data = $request->validate([
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'row_id')->where(function ($query) use ($tenant) {
                    $query->where('tenant_id', $tenant->row_id)
                        ->where('status', 'active');
                }),
            ],
            'domain' => ['nullable', 'string', 'max:255'],
        ]);

        $targetUser = isset($data['user_id']) ? User::query()->find($data['user_id']) : null;
        $domain = $data['domain'] ?? null;

        $result = $service->generateToken($tenant, $targetUser, $request->user(), $domain, $request);

        $audit->record(
            'tenant.impersonate',
            $tenant,
            User::class,
            $result['target_user']->row_id,
            sprintf('Impersonasi tenant [%s] sebagai user [%s].', $tenant->code, $result['target_user']->username),
            ['domain' => $domain],
        );

        if ($request->wantsJson() || $request->header('X-Inertia') === null) {
            return response()->json([
                'success' => true,
                'redirect_url' => $result['redirect_url'],
                'target_user' => [
                    'row_id' => $result['target_user']->row_id,
                    'name' => $result['target_user']->name,
                    'username' => $result['target_user']->username,
                ],
                'message' => "Token auto-login berhasil dibuat untuk tenant [{$tenant->name}].",
            ]);
        }

        return Inertia::location($result['redirect_url']);
    }
}
