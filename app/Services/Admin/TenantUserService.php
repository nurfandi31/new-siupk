<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Services\PhoneNormalizer;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class TenantUserService
{
    public function __construct(
        private TenantWorkbench $workbench,
        private PermissionChecker $permissions,
    ) {}

    public function create(Tenant $tenant, array $data): User
    {
        $user = DB::connection('platform')->transaction(function () use ($tenant, $data): User {
            $isVillage = ($data['role'] ?? null) === 'village_operator' || ! empty($data['is_village_user']);

            $user = User::query()->create([
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $tenant->row_id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => app(PhoneNormalizer::class)->normalize((string) ($data['phone'] ?? '')),
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
                'is_village_user' => $isVillage,
                'village_row_id' => $isVillage && ! empty($data['village_row_id']) ? (int) $data['village_row_id'] : null,
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenant->row_id,
                'user_id' => $user->row_id,
                'status' => ($data['status'] ?? 'active') === 'active' ? 'active' : 'suspended',
                'joined_at' => now(),
            ]);

            return $user;
        });

        $this->syncRole($tenant, $user, $data['role'] ?? null);

        return $user;
    }

    public function update(Tenant $tenant, User $user, array $data): User
    {
        $user = DB::connection('platform')->transaction(function () use ($user, $data): User {
            $isVillage = ($data['role'] ?? null) === 'village_operator' || ! empty($data['is_village_user']);

            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => app(PhoneNormalizer::class)->normalize((string) ($data['phone'] ?? '')),
                'username' => $data['username'],
                'status' => $data['status'],
                'is_village_user' => $isVillage,
                'village_row_id' => $isVillage && ! empty($data['village_row_id']) ? (int) $data['village_row_id'] : null,
            ])->save();

            if (! empty($data['password'])) {
                $user->forceFill(['password' => Hash::make($data['password'])])->save();
            }

            TenantMembership::query()
                ->where('user_id', $user->row_id)
                ->where('tenant_id', $user->tenant_id)
                ->update([
                    'status' => $data['status'] === 'active' ? 'active' : 'suspended',
                ]);

            return $user->fresh();
        });

        if (array_key_exists('role', $data)) {
            $this->syncRole($tenant, $user, $data['role'] ?: null);
        }

        return $user;
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => Hash::make($password)])->save();
    }

    /** @return list<string> */
    public function rolesFor(Tenant $tenant, User $user): array
    {
        return $this->workbench->run($tenant, function () use ($user): array {
            return UserRole::query()
                ->where('platform_user_id', (int) $user->row_id)
                ->with('role:row_id,code')
                ->get()
                ->map(fn (UserRole $ur): ?string => $ur->role?->code)
                ->filter()
                ->values()
                ->all();
        });
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, list<string>>
     */
    public function rolesForMany(Tenant $tenant, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->workbench->run($tenant, function () use ($userIds): array {
            $map = [];
            foreach ($userIds as $id) {
                $map[(int) $id] = [];
            }

            UserRole::query()
                ->whereIn('platform_user_id', $userIds)
                ->with('role:row_id,code')
                ->get()
                ->each(function (UserRole $ur) use (&$map): void {
                    $code = $ur->role?->code;
                    if ($code === null) {
                        return;
                    }
                    $map[(int) $ur->platform_user_id][] = $code;
                });

            return $map;
        });
    }

    private function syncRole(Tenant $tenant, User $user, ?string $roleCode): void
    {
        $this->workbench->run($tenant, function () use ($user, $roleCode): void {
            $this->permissions->ensureSystemRoles();

            UserRole::query()
                ->where('platform_user_id', (int) $user->row_id)
                ->delete();

            if ($roleCode === null || $roleCode === '') {
                return;
            }

            $this->permissions->assignRole($user, $roleCode);
        });
    }
}
