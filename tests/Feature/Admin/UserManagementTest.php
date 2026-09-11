<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        $this->tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tnt-test',
            'name' => 'Tenant Uji',
            'status' => 'active',
        ]);
    }

    public function test_superadmin_can_open_users_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_non_superadmin_cannot_access_users(): void
    {
        $user = $this->tenantUser();

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertRedirect();
    }

    public function test_superadmin_can_disable_and_reenable_user(): void
    {
        $admin = $this->superadmin();
        $target = $this->tenantUser();

        // Disable
        $this->actingAs($admin)
            ->post("/admin/users/{$target->row_id}/toggle-status")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['row_id' => $target->row_id, 'status' => 'suspended'], 'platform');

        // Login diblokir untuk user yang di-suspend (tanpa sesi superadmin aktif)
        $this->post('/logout');

        $this->from(route('login'))
            ->post('/login', ['identifier' => $target->username, 'password' => 'password'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Auth::guest());

        // Enable kembali
        $this->actingAs($admin)
            ->post("/admin/users/{$target->row_id}/toggle-status")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['row_id' => $target->row_id, 'status' => 'active'], 'platform');

        // Audit tercatat untuk kedua arah
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.disable'], 'platform');
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.enable'], 'platform');
    }

    public function test_superadmin_cannot_be_disabled(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post("/admin/users/{$admin->row_id}/toggle-status")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['row_id' => $admin->row_id, 'status' => 'active'], 'platform');
        $this->assertTrue(
            DB::connection('platform')->table('audit_logs')->where('action', 'user.disable')->doesntExist(),
        );
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

    private function tenantUser(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->row_id,
            'name' => 'Petugas Uji',
            'username' => 'petugas_'.Str::lower(Str::random(6)),
            'email' => 'petugas_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);
    }
}
