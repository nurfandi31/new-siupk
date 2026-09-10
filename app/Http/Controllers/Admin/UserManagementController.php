<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class UserManagementController
{
    /**
     * Pencarian pengguna lintas-tenant di tingkat platform beserta aksi
     * disable/enable akun (login diblokir via filter status=active pada AuthController).
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $tenantId = (int) $request->query('tenant_id', 0);
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        $paginator = User::query()
            ->with('tenant:row_id,name,code')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($tenantId > 0, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'row_id' => $user->row_id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
                'is_superadmin' => (bool) $user->is_superadmin,
                'tenant' => $user->tenant?->only(['row_id', 'name', 'code']),
                'last_login_at' => $user->last_login_at?->format('d/m/Y H:i'),
            ]);

        $summary = [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('status', 'active')->count(),
            'disabled_users' => User::query()->where('status', '!=', 'active')->count(),
            'without_tenant' => User::query()->whereNull('tenant_id')->where('is_superadmin', false)->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $paginator,
            'search' => $search,
            'status' => $status,
            'tenant_id' => $tenantId ?: null,
            'perPage' => $perPage,
            'summary' => $summary,
            'tenants' => Tenant::query()->orderBy('name')->get(['row_id', 'name', 'code']),
        ]);
    }

    public function toggleStatus(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        if ((bool) $user->is_superadmin) {
            return back()->with('error', 'Akun superadmin tidak dapat dinonaktifkan dari panel.');
        }

        if ((int) $user->getAuthIdentifier() === (int) auth()->id()) {
            return back()->with('error', 'Tidak dapat menonaktifkan akun sendiri.');
        }

        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        $before = ['status' => $user->status];

        DB::connection('platform')->transaction(function () use ($user, $newStatus): void {
            $user->forceFill(['status' => $newStatus])->save();

            TenantMembership::query()
                ->where('user_id', $user->row_id)
                ->update(['status' => $newStatus === 'active' ? 'active' : 'suspended']);
        });

        $audit->record(
            $newStatus === 'active' ? 'user.enable' : 'user.disable',
            $user->tenant_id !== null ? Tenant::query()->find($user->tenant_id) : null,
            User::class,
            $user->row_id,
            sprintf(
                'Akun user [%s] %s.',
                $user->username,
                $newStatus === 'active' ? 'diaktifkan kembali' : 'dinonaktifkan (akses login dicabut)',
            ),
            ['changes' => AuditLogger::diff($before, ['status' => $user->fresh()->status])],
        );

        return back()->with(
            'success',
            $newStatus === 'active'
                ? 'Akun pengguna diaktifkan kembali.'
                : 'Akun pengguna dinonaktifkan — login diblokir.',
        );
    }
}
