<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\AuditLog;
use App\Models\Platform\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $action = (string) $request->query('action', '');
        $tenantId = (int) $request->query('tenant_id', 0);
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        $logs = AuditLog::query()
            ->with(['tenant:row_id,name,code', 'actor:row_id,name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('description', 'like', "%{$search}%")
                ->orWhere('actor_name', 'like', "%{$search}%")
                ->orWhere('subject_type', 'like', "%{$search}%")))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($tenantId > 0, fn ($q) => $q->where('tenant_id', $tenantId))
            ->latest('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'row_id' => $log->row_id,
                'action' => $log->action,
                'actor_name' => $log->actor_name ?? ($log->actor?->name ?? 'Sistem'),
                'actor_type' => $log->actor_type,
                'tenant' => $log->tenant?->only(['row_id', 'name', 'code']),
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'description' => $log->description,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        // Aksi yang benar-benar ada di database — agar dropdown tidak berisi label kosong.
        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'search' => $search,
            'action' => $action,
            'tenant_id' => $tenantId ?: null,
            'perPage' => $perPage,
            'actions' => $actions,
            'tenants' => Tenant::query()->orderBy('name')->get(['row_id', 'name', 'code']),
        ]);
    }
}
