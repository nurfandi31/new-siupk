<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\UserRole;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Resolves tenant menu access per role.
 *
 * Rules:
 *  - superadmin / supervisor / operator desa → unrestricted
 *  - user without roles → unrestricted (legacy full access)
 *  - role with menu_access = null → unrestricted (follow module permissions)
 *  - role with menu_access array → only those menu keys are visible/allowed
 *
 * Menu access can only narrow module permissions; it never grants a page that
 * the role's permission pack does not allow.
 */
final class MenuAccess
{
    /** @var array<int, list<string>|null> */
    private array $cache = [];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, path: string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ((array) config('tenant_menus.groups', []) as $group) {
            $items = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (! isset($item['key'], $item['path'])) {
                    continue;
                }
                $items[] = [
                    'key' => (string) $item['key'],
                    'label' => (string) ($item['label'] ?? $item['key']),
                    'path' => (string) $item['path'],
                ];
            }

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => (string) ($group['key'] ?? 'group-'.count($groups)),
                'label' => (string) ($group['label'] ?? ''),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->groups() as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /** @return array<string, string> path => menu key */
    public function pathMap(): array
    {
        $map = [];
        foreach ($this->groups() as $group) {
            foreach ($group['items'] as $item) {
                $map[$item['path']] = $item['key'];
            }
        }

        return $map;
    }

    /**
     * Match a request path to a menu key using the longest path prefix.
     */
    public function match(string $path): ?string
    {
        $normalized = '/'.ltrim(strtok($path, '?') ?: $path, '/');
        $map = $this->pathMap();

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        $prefixes = array_keys($map);
        usort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($prefixes as $prefix) {
            if ($prefix === '/' || ! str_starts_with($normalized, $prefix)) {
                continue;
            }

            $next = substr($normalized, strlen($prefix), 1);
            if ($next === '' || $next === '/' || $next === '?' || $next === '#') {
                return $map[$prefix];
            }
        }

        return null;
    }

    /**
     * Allowed menu keys, or null when the user is unrestricted.
     *
     * @return list<string>|null
     */
    public function forUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if ($user->is_superadmin === true
            || $user->is_province_user === true
            || $user->is_regency_user === true
            || $user->is_village_user === true) {
            return null;
        }

        $userId = (int) $user->row_id;
        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        if (! $this->context->isInitialized() || ! $this->tablesReady()) {
            return $this->cache[$userId] = null;
        }

        $userRoles = UserRole::query()
            ->where('platform_user_id', $userId)
            ->with('role')
            ->get();

        if ($userRoles->isEmpty()) {
            return $this->cache[$userId] = null;
        }

        $allowed = [];
        foreach ($userRoles as $userRole) {
            $role = $userRole->role;
            if ($role === null) {
                continue;
            }

            // Administrator (or role not configured yet) keeps full menu access.
            if ($role->code === 'admin' || $role->menu_access === null) {
                return $this->cache[$userId] = null;
            }

            foreach ((array) $role->menu_access as $menuKey) {
                if (is_string($menuKey) && $menuKey !== '') {
                    $allowed[$menuKey] = true;
                }
            }
        }

        return $this->cache[$userId] = array_keys($allowed);
    }

    /**
     * Whether a path is allowed for the user (unrestricted users always pass).
     */
    public function allowsPath(?User $user, string $path): bool
    {
        $allowed = $this->forUser($user);
        if ($allowed === null) {
            return true;
        }

        $key = $this->match($path);

        return $key === null || in_array($key, $allowed, true);
    }

    private function tablesReady(): bool
    {
        try {
            $schema = DB::connection('tenant')->getSchemaBuilder();

            return $schema->hasTable('roles') && $schema->hasTable('user_roles');
        } catch (\Throwable) {
            return false;
        }
    }
}
