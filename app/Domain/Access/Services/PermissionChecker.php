<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Resolves effective permissions for a platform user inside the current tenant.
 *
 * Rules:
 *  - superadmin → all
 *  - zero roles → all (legacy full access; assign roles to restrict)
 *  - otherwise → union of role packs from config/permissions.php
 */
final class PermissionChecker
{
    /** @var array<int, list<string>|null> */
    private array $cache = [];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function allows(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_superadmin === true) {
            return true;
        }

        $effective = $this->effectivePermissions($user);
        if ($effective === null) {
            return true; // unrestricted (no roles assigned)
        }

        if (in_array('*', $effective, true)) {
            return true;
        }

        return in_array($permission, $effective, true);
    }

    public function denyUnless(?User $user, string $permission): void
    {
        if (! $this->allows($user, $permission)) {
            abort(403, "Missing permission: {$permission}");
        }
    }

    /**
     * @return list<string>
     */
    public function listFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->is_superadmin === true) {
            return ['*'];
        }

        $effective = $this->effectivePermissions($user);
        if ($effective === null) {
            return ['*'];
        }

        return $effective;
    }

    /**
     * null = unrestricted (no roles). list = explicit set (may include '*').
     *
     * @return list<string>|null
     */
    private function effectivePermissions(User $user): ?array
    {
        $userId = (int) $user->row_id;
        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        if (! $this->context->isInitialized()) {
            return $this->cache[$userId] = [];
        }

        if (! $this->rolesTableReady()) {
            return $this->cache[$userId] = null;
        }

        $userRoles = UserRole::query()
            ->where('platform_user_id', $userId)
            ->with('role')
            ->get();

        if ($userRoles->isEmpty()) {
            return $this->cache[$userId] = null;
        }

        $packs = config('permissions.roles', []);
        $perms = [];
        foreach ($userRoles as $ur) {
            $role = $ur->role;
            if ($role === null) {
                continue;
            }
            if ($role->code === 'admin') {
                $perms['*'] = true;

                continue;
            }
            $rolePerms = is_array($role->permissions)
                ? $role->permissions
                : ($packs[$role->code]['permissions'] ?? []);

            foreach ($rolePerms as $p) {
                $perms[$p] = true;
            }
        }

        return $this->cache[$userId] = array_keys($perms);
    }

    private function rolesTableReady(): bool
    {
        try {
            return DB::connection('tenant')->getSchemaBuilder()->hasTable('user_roles')
                && DB::connection('tenant')->getSchemaBuilder()->hasTable('roles');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ensure system roles from config exist for the current tenant.
     * Idempotent — safe to call from provision / login bootstrap.
     */
    public function ensureSystemRoles(): void
    {
        if (! $this->context->isInitialized() || ! $this->rolesTableReady()) {
            return;
        }

        foreach (config('permissions.roles', []) as $code => $def) {
            Role::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => (string) ($def['name'] ?? $code),
                    'is_system' => (bool) ($def['is_system'] ?? true),
                ],
            );
        }
    }

    public function assignRole(User $user, string $roleCode): void
    {
        $this->ensureSystemRoles();

        $role = Role::query()->where('code', $roleCode)->first();
        if ($role === null) {
            throw new \InvalidArgumentException("Unknown role [{$roleCode}]");
        }

        UserRole::query()->firstOrCreate(
            [
                'platform_user_id' => (int) $user->row_id,
                'role_row_id' => (int) $role->row_id,
            ],
            [],
        );

        unset($this->cache[(int) $user->row_id]);
    }
}
