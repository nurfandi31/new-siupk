<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Models\User;
use App\Services\Admin\TenantUserService;
use App\Services\TenantRegistrationService;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\FiscalPeriodProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantProvisionAndRoleTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_coa_and_fiscal_provisioners_seed_defaults(): void
    {
        $coa = app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        $this->assertGreaterThan(100, $coa['inserted'] + $coa['skipped']);
        $this->assertGreaterThan(0, Account::query()->count());

        $again = app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        $this->assertSame(0, $again['inserted']);
        $this->assertGreaterThan(0, $again['skipped']);

        $created = app(FiscalPeriodProvisioner::class)->ensureDefaults(1);
        $this->assertSame(12, $created);
        $this->assertSame(12, FiscalPeriod::query()->where('fiscal_year', (int) now()->year)->count());
    }

    public function test_admin_can_assign_role_to_tenant_user(): void
    {
        // Context was initialized by trait; clear so workbench can re-bind cleanly.
        $this->clearTenantTestContext();

        $admin = $this->superadmin();
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Satu',
            'username' => 'kasir1_'.Str::lower(Str::random(4)),
            'email' => 'kasir1_'.Str::lower(Str::random(4)).'@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put("/admin/tenants/{$this->testTenant->row_id}/users/{$user->row_id}", [
                'name' => 'Kasir Satu',
                'username' => $user->username,
                'email' => $user->email,
                'phone' => '081300000001',
                'status' => 'active',
                'role' => 'kasir',
            ])
            ->assertRedirect();

        $roles = app(TenantUserService::class)->rolesFor($this->testTenant, $user);
        $this->assertSame(['kasir'], $roles);
    }

    public function test_tenant_repair_is_idempotent(): void
    {
        $this->clearTenantTestContext();

        $result = app(TenantRegistrationService::class)->repair($this->testTenant);
        $this->assertArrayHasKey('coa', $result);
        $this->assertGreaterThanOrEqual(0, $result['coa']['inserted']);

        $second = app(TenantRegistrationService::class)->repair($this->testTenant);
        $this->assertSame(0, $second['coa']['inserted']);
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin_'.Str::lower(Str::random(6)),
            'email' => 'superadmin_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }
}
