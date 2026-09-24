<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Debug helper: print effective permissions for a given user, and whether
 * nav_map would surface /settings in the sidebar.
 *
 * Usage:
 *   php artisan siupk:inspect-user-perms {username}
 */
final class InspectUserPermissions extends Command
{
    protected $signature = 'siupk:inspect-user-perms
        {username : Username to inspect}
        {--tenant= : Tenant code (defaults to first active tenant)}';

    protected $description = 'Inspect a user\'s roles, effective permissions, and sidebar visibility for /settings.';

    public function handle(): int
    {
        $username = (string) $this->argument('username');

        $user = User::query()->where('username', $username)->first();
        if ($user === null) {
            $this->error("User [{$username}] not found in platform DB.");

            return self::FAILURE;
        }

        $this->line("User: <info>{$user->name}</info> (username={$user->username}, row_id={$user->row_id})");
        $this->line('  is_superadmin: '.($user->is_superadmin ? 'yes' : 'no'));
        $this->line('  is_province_user: '.($user->is_province_user ? 'yes' : 'no'));
        $this->line('  is_regency_user: '.($user->is_regency_user ? 'yes' : 'no'));
        $this->line('  is_village_user: '.($user->is_village_user ? 'yes' : 'no'));

        $tenantCode = (string) ($this->option('tenant') ?? '');
        if ($tenantCode === '') {
            $tenant = Tenant::query()->where('status', 'active')->first();
        } else {
            $tenant = Tenant::query()->where('code', $tenantCode)->first();
        }

        if ($tenant === null) {
            $this->warn('No tenant resolved — skipping role lookup.');

            return self::SUCCESS;
        }

        $this->line("Tenant: <info>{$tenant->code}</info> (row_id={$tenant->row_id})");

        $context = app(TenantContext::class);
        try {
            $placement = $tenant->placement;
            if ($placement === null || $placement->shard === null) {
                $this->warn('Tenant has no placement/shard; cannot initialize context.');

                return self::SUCCESS;
            }
            $context->initialize($tenant, $placement, $placement->shard);
        } catch (\Throwable $e) {
            $this->warn('Could not initialize tenant context: '.$e->getMessage());

            return self::SUCCESS;
        }

        $userRoles = UserRole::query()
            ->where('platform_user_id', (int) $user->row_id)
            ->with('role')
            ->get();

        if ($userRoles->isEmpty()) {
            $this->warn('  No roles assigned → effective permissions = unrestricted (*)');
        } else {
            $this->line('  Roles:');
            foreach ($userRoles as $ur) {
                $role = $ur->role;
                if ($role === null) {
                    $this->line("    - role_row_id={$ur->role_row_id} (missing)");

                    continue;
                }
                $perms = is_array($role->permissions) ? $role->permissions : [];
                $hasSettings = in_array('settings.manage', $perms, true) || in_array('*', $perms, true);
                $marker = $hasSettings ? '<info>✓ has settings.manage</info>' : '<comment>✗ no settings.manage</comment>';
                $this->line("    - {$role->code} ({$role->name}) {$marker}");
                if (! empty($perms)) {
                    $this->line('      permissions: '.implode(', ', $perms));
                }
            }
        }

        $checker = app(PermissionChecker::class);
        $perms = $checker->listFor($user);
        $this->line('  Effective permissions: '.(empty($perms) ? '(none)' : implode(', ', $perms)));

        $settingsAllowed = $checker->allows($user, 'settings.manage');
        $this->line('');
        $this->line('  /settings sidebar visibility: '.($settingsAllowed
            ? '<info>YES — menu will show</info>'
            : '<comment>NO — menu hidden (user lacks settings.manage)</comment>'));

        return self::SUCCESS;
    }
}
