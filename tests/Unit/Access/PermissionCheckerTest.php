<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\User;
use App\Tenancy\TenantContext;
use Tests\TestCase;

final class PermissionCheckerTest extends TestCase
{
    public function test_null_user_denied(): void
    {
        $checker = new PermissionChecker(app(TenantContext::class));
        self::assertFalse($checker->allows(null, 'journals.create'));
    }

    public function test_superadmin_allows_all(): void
    {
        $user = new User;
        $user->forceFill([
            'row_id' => 99,
            'is_superadmin' => true,
        ]);

        $checker = new PermissionChecker(app(TenantContext::class));
        self::assertTrue($checker->allows($user, 'journals.create'));
        self::assertTrue($checker->allows($user, 'loans.approve'));
        self::assertSame(['*'], $checker->listFor($user));
    }

    public function test_uninitialized_tenant_denies_non_superadmin(): void
    {
        $context = new TenantContext;
        self::assertFalse($context->isInitialized());

        $user = new User;
        $user->forceFill([
            'row_id' => 5,
            'is_superadmin' => false,
        ]);

        $checker = new PermissionChecker($context);
        // effectivePermissions returns [] when context missing → deny
        self::assertFalse($checker->allows($user, 'journals.create'));
    }
}
