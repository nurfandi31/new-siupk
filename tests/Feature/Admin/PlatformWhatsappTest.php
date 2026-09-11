<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\Tenant;
use App\Models\Platform\WhatsappPlatformInstance;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class PlatformWhatsappTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        DB::connection('platform')->disconnect();
        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        config()->set('services.wa_gateway', [
            'base_url' => 'https://wa-gateway.test',
            'api_key' => 'test-api-key',
            'timeout' => 15,
            'instance_prefix' => 'app-siupk',
        ]);

        Http::fake([
            'https://wa-gateway.test/*' => Http::response(['success' => true]),
        ]);
    }

    public function test_non_superadmin_cannot_access_platform_whatsapp(): void
    {
        $user = $this->tenantUser();

        $this->actingAs($user)
            ->get('/admin/whatsapp')
            ->assertRedirect();
    }

    public function test_superadmin_can_open_whatsapp_panel(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/whatsapp')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PlatformWhatsapp/Index')
                ->has('instances')
                ->where('configured', true));
    }

    public function test_superadmin_can_add_and_set_default_instance(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post('/admin/whatsapp', ['name' => 'OTP Platform', 'phone' => '081234567890'])
            ->assertRedirect('/admin/whatsapp');

        $this->assertDatabaseHas('whatsapp_platform_instances', [
            'name' => 'OTP Platform',
            'phone' => '6281234567890',
            'status' => 'disconnected',
            'is_default' => 1,
            'is_active' => 1,
        ], 'platform');

        $this->actingAs($admin)
            ->post('/admin/whatsapp', ['name' => 'OTP Cadangan', 'phone' => null]);

        $second = WhatsappPlatformInstance::query()->where('name', 'OTP Cadangan')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/whatsapp/{$second->row_id}/set-default")
            ->assertRedirect('/admin/whatsapp');

        $first = WhatsappPlatformInstance::query()->where('name', 'OTP Platform')->firstOrFail();
        $this->assertFalse((bool) $first->fresh()->is_default);
        $this->assertTrue((bool) $second->fresh()->is_default);
    }

    public function test_superadmin_can_create_poll_delete_and_test_gateway_session(): void
    {
        $admin = $this->superadmin();
        $instance = WhatsappPlatformInstance::query()->create([
            'name' => 'OTP Platform',
            'instance_name' => 'platform-wa-otp',
            'phone' => '6281234567890',
            'status' => 'disconnected',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post("/admin/whatsapp/{$instance->row_id}/create-session")
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('test-api-key'))
            && $request->url() === 'https://wa-gateway.test/create-instance'
            && $request['instance'] === 'platform-wa-otp');

        $this->actingAs($admin)
            ->get("/admin/whatsapp/{$instance->row_id}/state")
            ->assertOk()
            ->assertJsonPath('state', 'connecting');

        Http::assertSent(fn ($request) => $request->url() === 'https://wa-gateway.test/instance-state?instance=platform-wa-otp');

        $this->actingAs($admin)
            ->post("/admin/whatsapp/{$instance->row_id}/test", [
                'phone' => '081234567890',
                'message' => 'Tes platform',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn ($request) => $request->url() === 'https://wa-gateway.test/send-message'
            && $request['number'] === '6281234567890'
            && $request['text'] === 'Tes platform');

        $this->actingAs($admin)
            ->delete("/admin/whatsapp/{$instance->row_id}/delete-session")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSame('disconnected', $instance->fresh()->status);
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin_'.Str::lower(Str::random(6)),
            'email' => 'superadmin_'.Str::lower(Str::random(6)).'@example.test',
            'phone' => '6281234567890',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }

    private function tenantUser(): User
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-'.Str::lower(Str::random(8)),
            'name' => 'Tenant Uji',
            'status' => 'active',
        ]);

        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => 'Petugas Uji',
            'username' => 'petugas_'.Str::lower(Str::random(6)),
            'email' => 'petugas_'.Str::lower(Str::random(6)).'@example.test',
            'phone' => '6289876543210',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);
    }
}
