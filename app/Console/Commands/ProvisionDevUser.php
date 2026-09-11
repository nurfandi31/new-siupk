<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class ProvisionDevUser extends Command
{
    protected $signature = 'siupk:provision-dev-user
        {--username=dev : Development username}
        {--email=dev@example.test : Development email}
        {--password= : Password; required in non-interactive mode}
        {--tenant=local : Tenant code for membership}';

    protected $description = 'Provision an idempotent local development user and tenant membership.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('This command is restricted to local and testing environments.');
        }

        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if ($password === '') {
            $this->error('A password is required.');

            return self::INVALID;
        }

        $tenant = Tenant::query()->where('code', (string) $this->option('tenant'))->first();

        if ($tenant === null) {
            $this->error("Tenant [{$this->option('tenant')}] does not exist.");

            return self::FAILURE;
        }

        $user = User::query()->where('username', (string) $this->option('username'))->first();

        if ($user !== null && $user->tenant_id !== null && (int) $user->tenant_id !== (int) $tenant->row_id) {
            $this->error("User [{$user->username}] already belongs to another tenant.");

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['username' => (string) $this->option('username')],
            [
                'tenant_id' => $tenant->row_id,
                'public_id' => $user?->public_id ?? (string) Str::ulid(),
                'name' => 'Development User',
                'email' => (string) $this->option('email'),
                'password' => Hash::make($password),
                'status' => 'active',
            ],
        );

        TenantMembership::query()->updateOrCreate(
            ['user_id' => $user->row_id],
            ['tenant_id' => $tenant->row_id, 'status' => 'active', 'joined_at' => now()],
        );

        $this->info("Provisioned user [{$user->username}] with membership [{$tenant->code}].");

        return self::SUCCESS;
    }
}
