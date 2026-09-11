<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Documents\Services\SignatureImageService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SignatureDigitalTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private string $samplePngBase64;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        Storage::fake('public');

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Admin Settings',
            'email' => 'admin_settings@example.test',
            'username' => 'admin_settings',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        // 1x1 transparent PNG base64
        $this->samplePngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    public function test_settings_page_renders_signature_images_prop(): void
    {
        $response = $this->actingAs($this->user)->get('/settings?tab=signatures');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Index')
            ->has('signatures')
            ->has('signatureImages')
        );
    }

    public function test_can_upload_and_delete_signature_image(): void
    {
        $postResponse = $this->actingAs($this->user)->post('/settings/signatures/image', [
            'report_key' => 'default',
            'image' => $this->samplePngBase64,
        ]);

        $postResponse->assertRedirect('/settings?tab=signatures');

        $service = app(SignatureImageService::class);
        $this->assertNotNull($service->path('default'));
        $this->assertNotNull($service->dataUri('default'));

        $deleteResponse = $this->actingAs($this->user)->delete('/settings/signatures/image', [
            'report_key' => 'default',
        ]);

        $deleteResponse->assertRedirect('/settings?tab=signatures');

        $service = app(SignatureImageService::class);
        $this->assertNull($service->path('default'));
    }
}
