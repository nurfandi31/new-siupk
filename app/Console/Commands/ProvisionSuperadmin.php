<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class ProvisionSuperadmin extends Command
{
    protected $signature = 'siupk:provision-superadmin
        {--username=superadmin : Superadmin username}
        {--email= : Superadmin email}
        {--password= : Password; required in non-interactive mode}';

    protected $description = 'Provision an idempotent platform superadmin for local and testing environments.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('This command is restricted to local and testing environments.');
        }

        $password = (string) ($this->option('password') ?: $this->secret('Password'));
        $email = (string) ($this->option('email') ?: 'superadmin@example.test');

        if ($password === '') {
            $this->error('A password is required.');

            return self::INVALID;
        }

        User::query()->updateOrCreate(
            ['username' => (string) $this->option('username')],
            [
                'public_id' => User::query()->where('username', $this->option('username'))->value('public_id') ?: (string) Str::ulid(),
                'name' => 'Superadmin',
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'tenant_id' => null,
                'is_superadmin' => true,
            ],
        );

        $this->info("Provisioned superadmin [{$this->option('username')}].");

        return self::SUCCESS;
    }
}
