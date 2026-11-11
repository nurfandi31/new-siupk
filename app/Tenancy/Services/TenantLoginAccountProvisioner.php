<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds one login account per tenant role.
 *
 * The accounts are derived from config/permissions.php, so a new location is
 * immediately usable without filling in the "first user" form manually while
 * still allowing each role to log in with its own credentials.
 */
final readonly class TenantLoginAccountProvisioner
{
    public function __construct(
        private PermissionChecker $permissions,
    ) {}

    /**
     * Create missing accounts and re-attach roles for existing ones.
     *
     * @return list<array{role: string, role_name: string, username: string, email: string, created: bool, initial_password: ?string}>
     */
    public function ensureDefaults(Tenant $tenant): array
    {
        if (! (bool) config('tenancy.seed_users.enabled', true)) {
            return [];
        }

        $roles = config('permissions.roles', []);
        if (! is_array($roles) || $roles === []) {
            return [];
        }

        $configuredPassword = trim((string) config('tenancy.seed_users.password', ''));
        $password = $configuredPassword !== '' ? $configuredPassword : Str::password(16);
        $accounts = [];

        foreach ($roles as $roleCode => $definition) {
            $roleCode = (string) $roleCode;
            $roleName = (string) ($definition['name'] ?? $roleCode);
            $username = mb_substr($tenant->code.'.'.$roleCode, 0, 100);
            $email = mb_substr($username.'@tenant.local', 0, 190);

            [$user, $created] = DB::connection('platform')->transaction(function () use ($tenant, $roleCode, $roleName, $username, $email, $password): array {
                $user = User::query()->where('username', $username)->first();

                if ($user === null) {
                    $user = User::query()->create([
                        'public_id' => (string) Str::ulid(),
                        'tenant_id' => $tenant->row_id,
                        'name' => mb_substr($roleName.' '.$tenant->name, 0, 150),
                        'email' => $email,
                        'username' => $username,
                        'password' => Hash::make($password),
                        'phone' => $this->placeholderPhone($username),
                        'status' => 'active',
                        'is_village_user' => $roleCode === 'village_operator',
                    ]);
                    $created = true;
                } else {
                    $created = false;
                }

                TenantMembership::query()->firstOrCreate(
                    ['user_id' => $user->row_id],
                    [
                        'tenant_id' => $tenant->row_id,
                        'status' => 'active',
                        'joined_at' => now(),
                    ],
                );

                return [$user, $created];
            });

            $this->permissions->assignRole($user, $roleCode);

            $accounts[] = [
                'role' => $roleCode,
                'role_name' => $roleName,
                'username' => $username,
                'email' => $email,
                'created' => $created,
                'initial_password' => $created ? $password : null,
            ];
        }

        return $accounts;
    }

    private function placeholderPhone(string $username): string
    {
        return 'pending-wa-'.substr(md5($username), 0, 8);
    }
}
