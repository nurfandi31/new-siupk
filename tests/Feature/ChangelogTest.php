<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class ChangelogTest extends TestCase
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
            'name' => 'Petugas Changelog',
            'email' => 'changelog@example.test',
            'username' => 'petugas_changelog',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_from_changelog(): void
    {
        $response = $this->get('/changelog');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_changelog(): void
    {
        $response = $this->actingAs($this->user)->get('/changelog');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Changelog/Index')
            ->has('releases')
            ->has('latest_version')
            ->has('total_releases')
            ->where('total_releases', fn ($count) => is_int($count) && $count > 0)
        );
    }

    public function test_superadmin_can_view_admin_changelog(): void
    {
        $superadmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'email' => 'superadmin@example.test',
            'username' => 'superadmin_cl',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        $response = $this->actingAs($superadmin)->get('/admin/changelog');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Changelog/Index')
            ->has('releases')
        );
    }
}
