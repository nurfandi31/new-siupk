<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class ChartOfAccountsTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir COA',
            'email' => 'coa@example.test',
            'username' => 'coa_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $createdAt = CarbonImmutable::parse('2026-01-02');
        $deactivatedAt = CarbonImmutable::parse('2026-03-31');

        $parent = Account::query()->create([
            'code' => '1',
            'name' => 'Aset',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 1,
            'is_postable' => false,
            'is_active' => true,
            'created_at' => $createdAt,
        ]);
        Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Kas',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
            'parent_row_id' => $parent->row_id,
            'created_at' => $createdAt,
        ]);
        Account::query()->create([
            'code' => '4.1.01',
            'name' => 'Pendapatan Jasa',
            'account_type' => 'revenue',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $createdAt,
        ]);
        Account::query()->create([
            'code' => '9.9.99',
            'name' => 'Akun Mati',
            'account_type' => 'expense',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => false,
            'created_at' => $createdAt,
            'deactivated_at' => $deactivatedAt,
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_index_lists_accounts_read_only(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/chart-of-accounts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/ChartOfAccounts/Index')
                ->has('rows', 4)
                ->where('counts.total', 4)
                ->where('counts.active', 3)
                ->where('counts.postable', 2)
                ->where('rows.0.created_at', '2026-01-02')
                ->where('rows.3.deactivated_at', '2026-03-31')
            );
    }

    public function test_index_filters_by_type_and_status(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/chart-of-accounts?type=asset&status=active')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/ChartOfAccounts/Index')
                ->has('rows', 2)
                ->where('filters.type', 'asset')
                ->where('filters.status', 'active')
            );
    }

    public function test_index_searches_by_code(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/chart-of-accounts?q=1.1.01')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.code', '1.1.01')
            );
    }
}
