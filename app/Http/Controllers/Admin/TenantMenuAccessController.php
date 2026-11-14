<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Services\MenuAccess;
use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Tenant;
use App\Services\Admin\AuditLogger;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan hak akses menu tenant dari panel pusat.
 *
 * Setiap role tenant bisa dibatasi ke daftar menu tertentu. Role Administrator
 * selalu penuh dan role yang belum dikonfigurasi (menu_access = null) tetap
 * mengikuti izin modul seperti sebelumnya.
 */
final class TenantMenuAccessController
{
    private const EXCLUDED_ROLES = ['regency_supervisor', 'province_supervisor'];

    public function index(Tenant $tenant, TenantWorkbench $workbench, MenuAccess $menus): Response
    {
        $error = null;

        try {
            $roles = $workbench->run($tenant, function () use ($menus): array {
                app(PermissionChecker::class)->ensureSystemRoles();

                return Role::query()
                    ->whereNotIn('code', self::EXCLUDED_ROLES)
                    ->withCount('userRoles')
                    ->orderByDesc('is_system')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Role $role): array => [
                        'row_id' => (int) $role->row_id,
                        'code' => (string) $role->code,
                        'name' => (string) $role->name,
                        'is_system' => (bool) $role->is_system,
                        'is_locked' => $role->code === 'admin',
                        'user_count' => (int) $role->user_roles_count,
                        'restricted' => $role->menu_access !== null,
                        'menu_count' => $role->menu_access === null
                            ? count($menus->keys())
                            : count((array) $role->menu_access),
                    ])
                    ->all();
            });
        } catch (\Throwable $e) {
            report($e);
            $roles = [];
            $error = 'Tenant belum siap (placement/shard belum lengkap). Jalankan provisioning terlebih dahulu.';
        }

        return Inertia::render('Admin/Tenants/Menus/Index', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'roles' => $roles,
            'totalMenus' => count($menus->keys()),
            'error' => $error,
        ]);
    }

    public function edit(Tenant $tenant, string $role, TenantWorkbench $workbench, MenuAccess $menus): Response|RedirectResponse
    {
        try {
            $payload = $workbench->run($tenant, function () use ($role, $menus): array {
                $record = Role::query()->findOrFail((int) $role);

                return [
                    'role' => [
                        'row_id' => (int) $record->row_id,
                        'code' => (string) $record->code,
                        'name' => (string) $record->name,
                        'is_system' => (bool) $record->is_system,
                        'is_locked' => $record->code === 'admin',
                        'restricted' => $record->menu_access !== null,
                        'selected' => $record->menu_access === null
                            ? $menus->keys()
                            : array_values(array_intersect($menus->keys(), (array) $record->menu_access)),
                    ],
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return to_route('admin.tenants.menus.index', $tenant)
                ->with('error', 'Role tidak dapat dibuka. Pastikan tenant sudah di-provision dengan benar.');
        }

        return Inertia::render('Admin/Tenants/Menus/Edit', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'role' => $payload['role'],
            'menuGroups' => $menus->groups(),
            'totalMenus' => count($menus->keys()),
        ]);
    }

    public function update(
        Request $request,
        Tenant $tenant,
        string $role,
        TenantWorkbench $workbench,
        MenuAccess $menus,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'restricted' => ['nullable', 'boolean'],
            'menus' => ['array'],
            'menus.*' => ['string', Rule::in($menus->keys())],
        ]);

        $allKeys = $menus->keys();
        $restricted = (bool) ($data['restricted'] ?? false);
        $selected = array_values(array_unique((array) ($data['menus'] ?? [])));

        // Semua menu dicentang = kembali ke mode default (mengikuti izin modul).
        $menuAccess = $restricted && count($selected) < count($allKeys)
            ? array_values(array_intersect($allKeys, $selected))
            : null;

        $result = $workbench->run($tenant, function () use ($role, $menuAccess): ?array {
            $record = Role::query()->findOrFail((int) $role);

            if ($record->code === 'admin') {
                return null;
            }

            $before = $record->menu_access;
            $record->forceFill(['menu_access' => $menuAccess])->save();

            return [
                'row_id' => (int) $record->row_id,
                'code' => (string) $record->code,
                'before' => $before,
                'after' => $menuAccess,
            ];
        });

        if ($result === null) {
            return back()->with('error', 'Role Administrator selalu memiliki akses penuh dan tidak dapat dibatasi.');
        }

        $audit->record(
            'tenant_role.menu_access',
            $tenant,
            Role::class,
            $result['row_id'],
            sprintf('Hak akses menu role [%s] pada tenant [%s] diperbarui.', $result['code'], $tenant->code),
            ['changes' => AuditLogger::diff(
                ['menu_access' => $result['before']],
                ['menu_access' => $result['after']],
            )],
        );

        return to_route('admin.tenants.menus.index', $tenant)
            ->with('success', 'Hak akses menu berhasil disimpan.');
    }
}
