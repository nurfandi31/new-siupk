<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\Tenant;
use App\Models\User;
use App\Services\PlatformSettingService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformSettingsTest extends TestCase
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

    public function test_superadmin_can_open_settings_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/settings')
            ->assertOk();
    }

    public function test_non_superadmin_cannot_access_settings(): void
    {
        $this->actingAs($this->tenantUser())
            ->get('/admin/settings')
            ->assertRedirect();
    }

    public function test_superadmin_can_update_and_delete_setting(): void
    {
        $admin = $this->superadmin();

        // Simpan setting biasa (int)
        $this->actingAs($admin)
            ->post('/admin/settings', ['key' => 'wa.template_billing', 'value' => '42', 'value_type' => 'int'])
            ->assertRedirect();

        $settings = app(PlatformSettingService::class);
        $this->assertSame(42, $settings->get('wa.template_billing'));

        // Update nilai yang sama
        $this->post('/admin/settings', ['key' => 'wa.template_billing', 'value' => '100', 'value_type' => 'int'])
            ->assertRedirect();
        $settings->flush(); // buang cache instance agar membaca ulang dari DB
        $this->assertSame(100, $settings->get('wa.template_billing'));

        // Audit tercatat
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform_setting.update'], 'platform');

        // Hapus
        $this->delete('/admin/settings', ['key' => 'wa.template_billing'])
            ->assertRedirect();
        $settings->flush(); // buang cache instance agar membaca ulang dari DB
        $this->assertNull($settings->get('wa.template_billing'));
        $this->assertTrue(
            DB::connection('platform')->table('platform_settings')->where('key', 'wa.template_billing')->doesntExist(),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform_setting.delete'], 'platform');
    }

    public function test_sensitive_key_is_encrypted_and_masked(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post('/admin/settings', ['key' => 'tripay.api_key', 'value' => 'rahasia-123', 'value_type' => 'string'])
            ->assertRedirect();

        // Nilai mentah TIDAK tersimpan sebagai plaintext
        $row = DB::connection('platform')->table('platform_settings')->where('key', 'tripay.api_key')->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('rahasia-123', (string) $row->value);
        $this->assertNotSame('rahasia-123', $row->value);

        // Service tetap bisa membaca nilainya (terdekripsi)
        $this->assertSame('rahasia-123', app(PlatformSettingService::class)->getEncrypted('tripay.api_key'));

        // Halaman tidak pernah mengirim nilai sensitif ke browser
        $response = $this->get('/admin/settings?search=tripay');
        $response->assertOk();
        $response->assertDontSee('rahasia-123');
        $content = $response->getContent();
        $masked = str_contains($content, chr(0x2022));
        $this->assertTrue($masked, 'Halaman tidak menampilkan masker nilai terenkripsi.');

        // Audit memakai masker, bukan nilai asli
        $audit = DB::connection('platform')->table('audit_logs')
            ->where('action', 'platform_setting.update')->latest('row_id')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('rahasia-123', (string) $audit->properties);
    }

    public function test_invalid_json_value_is_rejected(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post('/admin/settings', ['key' => 'some.json_config', 'value' => '{"broken": ', 'value_type' => 'json'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue(
            DB::connection('platform')->table('platform_settings')->where('key', 'some.json_config')->doesntExist(),
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
