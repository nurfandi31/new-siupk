<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domain\Notifications\Models\WhatsappInstance;
use App\Models\User;
use App\Services\WhatsappGatewayService;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class WhatsappManagementTest extends TestCase
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
            'name' => 'Admin WA',
            'email' => 'admin_wa@example.test',
            'username' => 'admin_wa',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_can_view_whatsapp_management_page(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/whatsapp/manage');
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Whatsapp/Hub')
            ->has('instances')
            ->has('global')
        );
    }

    public function test_can_create_and_manage_multiple_instances(): void
    {
        $this->actingAs($this->user)->post('/settings/whatsapp/instances', [
            'name' => 'CS 1',
            'phone_number' => '08123456789',
            'daily_limit' => 500,
            'is_active' => true,
        ])->assertRedirect('/settings/whatsapp/manage');

        $this->assertDatabaseHas('whatsapp_instances', [
            'name' => 'CS 1',
            'is_default' => 1,
            'is_active' => 1,
        ], 'tenant');

        $this->actingAs($this->user)->post('/settings/whatsapp/instances', [
            'name' => 'CS 2',
            'phone_number' => '08987654321',
            'daily_limit' => 1000,
            'is_active' => true,
            'is_default' => false,
        ])->assertRedirect('/settings/whatsapp/manage');

        $this->assertDatabaseHas('whatsapp_instances', [
            'name' => 'CS 2',
            'is_default' => 0,
        ], 'tenant');

        $this->assertSame(2, WhatsappInstance::query()->count());

        $cs2 = WhatsappInstance::query()->where('name', 'CS 2')->firstOrFail();
        $this->actingAs($this->user)->put('/settings/whatsapp/instances/'.$cs2->row_id, [
            'name' => 'CS 2 Utama',
            'phone_number' => '08987654321',
            'daily_limit' => 1000,
            'is_active' => true,
            'is_default' => true,
        ])->assertRedirect('/settings/whatsapp/manage');

        $cs1 = WhatsappInstance::query()->where('name', 'CS 1')->firstOrFail();
        $this->assertFalse((bool) $cs1->fresh()->is_default);
        $this->assertTrue((bool) $cs2->fresh()->is_default);

        $gateway = app(WhatsappGatewayService::class);
        $gateway->setRotationMode('round_robin');
        $this->assertNotSame('', $gateway->resolveActiveInstance());

        $this->actingAs($this->user)->delete('/settings/whatsapp/instances/'.$cs2->row_id)
            ->assertRedirect('/settings/whatsapp/manage');
        $this->assertSame(1, WhatsappInstance::query()->count());
    }

    public function test_can_update_global_whatsapp_settings(): void
    {
        $this->actingAs($this->user)->put('/settings/whatsapp/global', [
            'template_billing' => 'Template tagihan khusus',
            'template_installment' => 'Template angsuran khusus',
            'is_enabled' => true,
            'rotation_mode' => 'round_robin',
        ])->assertRedirect('/settings/whatsapp');

        $gateway = app(WhatsappGatewayService::class);
        $this->assertTrue($gateway->isEnabled());
        $this->assertSame('round_robin', $gateway->getRotationMode());
    }
}
