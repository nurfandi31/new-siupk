<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Domain\Documents\Services\SignatureImageService;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Services\TenantSettingService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SignatureImageServiceUnitTest extends TestCase
{
    private string $samplePngBase64;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
        DB::purge('tenant');
        DB::connection('tenant')->statement('CREATE TABLE tenant_settings (tenant_id INTEGER, key TEXT, value TEXT, value_type TEXT, created_at TEXT, updated_at TEXT)');

        Storage::fake('public');
        $this->samplePngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    private function makeService(): SignatureImageService
    {
        $tenant = new Tenant;
        $tenant->row_id = 99;
        $placement = new TenantPlacement;
        $placement->tenant_id = 99;
        $placement->shard_id = 1;
        $shard = new DatabaseShard;
        $shard->row_id = 1;

        $context = new TenantContext;
        $context->initialize($tenant, $placement, $shard);

        $settings = new TenantSettingService($context);

        return new SignatureImageService($settings, $context);
    }

    public function test_stores_and_retrieves_signature_image_correctly(): void
    {
        $service = $this->makeService();

        $path = $service->store('default', $this->samplePngBase64);
        $this->assertSame('tenants/99/signatures/default.png', $path);
        Storage::disk('public')->assertExists($path);

        $dataUri = $service->dataUri('default');
        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $service->delete('default');
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($service->path('default'));
    }

    public function test_rejects_unknown_report_key(): void
    {
        $this->expectException(\RuntimeException::class);
        $service = $this->makeService();
        $service->store('non_existent_report', $this->samplePngBase64);
    }
}
