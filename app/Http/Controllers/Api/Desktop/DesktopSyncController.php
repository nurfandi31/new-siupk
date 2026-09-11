<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Desktop;

use App\Domain\Billing\Services\SubscriptionGateService;
use App\Domain\Desktop\Services\UpdateManifestService;
use App\Domain\Sync\Services\DesktopPushApplyService;
use App\Domain\Sync\Services\TenantSnapshotService;
use App\Models\Platform\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DesktopSyncController
{
    public function __construct(
        private readonly TenantSnapshotService $snapshotService,
        private readonly DesktopPushApplyService $pushService,
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionGateService $subscriptionGate,
        private readonly UpdateManifestService $updateManifest,
    ) {}

    /**
     * Full Snapshot Export for Desktop SQLite Initialization.
     */
    public function snapshot(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        $payload = $this->snapshotService->export($targetTenant, null);

        return response()->json([
            'status' => 'success',
            'message' => 'Full tenant snapshot generated successfully.',
            ...$payload,
        ]);
    }

    public function push(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        if ($this->updateManifest->outdated($request->header('X-App-Version'))) {
            return response()->json([
                'status' => 'error',
                'code' => 'CLIENT_OUTDATED',
                'min_supported_version' => (string) config('desktop-update.min_version'),
            ], 426);
        }

        $subscriptionGate = $this->subscriptionGate->check((int) $targetTenant->row_id);
        $update = $this->updateManifest->manifest((string) $request->query('current_version', ''));
        if ($subscriptionGate['blocked']) {
            return response()->json([
                'status' => 'blocked',
                'code' => 'SUBSCRIPTION_BLOCKED',
                'message' => $subscriptionGate['message'],
                'invoice_number' => $subscriptionGate['invoice_number'],
            ], 402);
        }

        $validated = $request->validate([
            'mutations' => ['required', 'array', 'max:200'],
            'mutations.*.mutation_uuid' => ['required', 'uuid'],
            'mutations.*.table_name' => ['required', 'string', 'max:100'],
            'mutations.*.operation' => ['required', 'string', 'in:insert,update,delete'],
            'mutations.*.row_public_id' => ['required', 'integer'],
            'mutations.*.payload' => ['required', 'array'],
            'mutations.*.client_updated_at' => ['nullable', 'date'],
            'last_pulled_at' => ['nullable', 'date'],
        ]);

        $result = $this->pushService->apply(
            $targetTenant,
            $validated['mutations'],
            isset($validated['last_pulled_at']) ? (string) $validated['last_pulled_at'] : null,
        );

        return response()->json([
            'status' => 'success',
            ...$result,
        ]);
    }

    /**
     * Incremental / Delta Snapshot Export for Desktop Synchronization.
     */
    public function delta(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        $since = (string) $request->query('since', '');
        if (trim($since) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'The [since] query parameter (ISO8601 timestamp) is required for delta synchronization.',
            ], 422);
        }

        $payload = $this->snapshotService->export($targetTenant, $since);

        return response()->json([
            'status' => 'success',
            'message' => 'Delta tenant snapshot generated successfully.',
            ...$payload,
        ]);
    }

    /**
     * Quick status check and metadata for desktop app.
     */
    public function status(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        $subscriptionGate = $this->subscriptionGate->check((int) $targetTenant->row_id);
        $update = $this->updateManifest->manifest($request->query('current_version'));

        return response()->json([
            'status' => 'success',
            'server_time' => now()->toIso8601String(),
            'app_name' => config('app.name', 'siupk Next'),
            'app_version' => (string) config('desktop-update.server_version'),
            'tenant' => [
                'id' => (int) $targetTenant->row_id,
                'code' => (string) $targetTenant->code,
                'name' => (string) $targetTenant->name,
                'status' => (string) $targetTenant->status,
                'district_code' => $targetTenant->district_code,
                'regency_code' => $targetTenant->regency_code,
                'regency_name' => $targetTenant->regency_name,
                'province_code' => $targetTenant->province_code,
                'shard' => $targetTenant->placement?->shard?->code,
            ],
            'subscription' => [
                'blocked' => $subscriptionGate['blocked'],
                'reason' => $subscriptionGate['reason'],
                'invoice_number' => $subscriptionGate['invoice_number'],
                'message' => $subscriptionGate['message'],
            ],
            'update' => [
                'update_available' => $update['update_available'],
                'latest_version' => $update['latest_version'],
                'force_update' => $update['force_update'],
            ],
        ]);
    }

    public function updateCheck(Request $request): JsonResponse
    {
        $update = $this->updateManifest->manifest($request->query('current_version'));
        $tenant = $this->resolveTenant($request, null);

        if ($tenant === null) {
            return response()->json([
                ...$update,
                'subscription' => [
                    'blocked' => false,
                    'reason' => null,
                    'message' => null,
                    'invoice_number' => null,
                ],
            ]);
        }

        $subscriptionGate = $this->subscriptionGate->check((int) $tenant->row_id);

        return response()->json([
            ...$update,
            'subscription' => [
                'blocked' => $subscriptionGate['blocked'],
                'reason' => $subscriptionGate['reason'],
                'message' => $subscriptionGate['message'],
                'invoice_number' => $subscriptionGate['invoice_number'],
            ],
        ]);
    }

    private function resolveTenant(Request $request, ?string $routeTenant): ?Tenant
    {
        $identifier = $routeTenant
            ?? $request->header('X-Tenant-Code')
            ?? $request->query('tenant')
            ?? $request->query('tenant_id');

        if (is_string($identifier) && trim($identifier) !== '') {
            $trimmed = trim($identifier);
            $query = Tenant::query()->with(['placement.shard'])->whereIn('status', ['active', 'read_only']);

            return is_numeric($trimmed)
                ? $query->where('row_id', (int) $trimmed)->first()
                : $query->where('code', $trimmed)->first();
        }

        if ($this->tenantContext->isInitialized()) {
            return $this->tenantContext->tenant();
        }

        $user = $request->user();
        if ($user !== null && $user->getAttribute('tenant_id') !== null) {
            $tenantId = (int) $user->getAttribute('tenant_id');

            return Tenant::query()->with(['placement.shard'])->where('row_id', $tenantId)->first();
        }

        return null;
    }

    private function tenantNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Tenant not found. Please provide a valid tenant code or ID.',
        ], 404);
    }
}
