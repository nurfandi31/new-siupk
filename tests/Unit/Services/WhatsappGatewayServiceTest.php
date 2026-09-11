<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Services\TenantSettingService;
use App\Services\WhatsappGatewayService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

final class WhatsappGatewayServiceTest extends TestCase
{
    private function createService(HttpFactory $http, array $settingsMap = []): WhatsappGatewayService
    {
        $tenant = new Tenant;
        $tenant->row_id = 1;
        $tenant->id = 'lkd-1';

        $placement = new TenantPlacement;
        $placement->tenant_id = 1;
        $placement->shard_id = 1;

        $shard = new DatabaseShard;
        $shard->row_id = 1;

        $context = app(TenantContext::class);
        $context->initialize($tenant, $placement, $shard);

        $settingsRef = new ReflectionClass(TenantSettingService::class);
        /** @var TenantSettingService $settings */
        $settings = $settingsRef->newInstanceWithoutConstructor();
        $cacheProp = $settingsRef->getProperty('cache');
        $cacheProp->setValue($settings, $settingsMap);
        $contextProp = $settingsRef->getProperty('context');
        $contextProp->setValue($settings, $context);

        return new WhatsappGatewayService($settings, $context, $http);
    }

    public function test_instance_name_has_app_prefix(): void
    {
        config(['services.wa_gateway.instance_prefix' => 'app-siupk']);
        $http = new HttpFactory;
        $service = $this->createService($http);

        $this->assertSame('app-siupk-1', $service->getInstance());
    }

    public function test_normalize_phone(): void
    {
        $http = new HttpFactory;
        $service = $this->createService($http);

        $this->assertSame('628123456789', $service->normalizePhone('08123456789'));
        $this->assertSame('628123456789', $service->normalizePhone('+62 812-3456-789'));
        $this->assertSame('628123456789', $service->normalizePhone('8123456789'));
    }

    public function test_create_instance_endpoint(): void
    {
        config([
            'services.wa_gateway.base_url' => 'https://agent.siupk.net/webhook-test',
            'services.wa_gateway.api_key' => 'enpii:its.enpii-118',
            'services.wa_gateway.instance_prefix' => 'app-siupk',
        ]);

        $http = new HttpFactory;
        $http->fake([
            'https://agent.siupk.net/webhook-test/create-instance' => Http::response([
                'success' => true,
                'instance' => [
                    'name' => 'app-siupk-1',
                    'status' => 'connecting',
                    'qr' => 'data:image/png;base64,iVBORw...',
                ],
            ], 200),
        ]);

        $service = $this->createService($http);
        $res = $service->createInstance();

        $this->assertTrue($res['success']);
        $this->assertSame('data:image/png;base64,iVBORw...', $res['qr']);
        $this->assertSame('app-siupk-1', $res['instance']);

        $http->assertSent(function (Request $request) {
            return $request->url() === 'https://agent.siupk.net/webhook-test/create-instance'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('enpii:its.enpii-118'))
                && $request['instance'] === 'app-siupk-1';
        });
    }

    public function test_send_single_message_endpoint(): void
    {
        config([
            'services.wa_gateway.base_url' => 'https://agent.siupk.net/webhook-test',
            'services.wa_gateway.api_key' => 'enpii:its.enpii-118',
        ]);

        $http = new HttpFactory;
        $http->fake([
            'https://agent.siupk.net/webhook-test/send-message' => Http::response(['success' => true], 200),
        ]);

        $service = $this->createService($http, ['whatsapp.is_enabled' => true]);
        $res = $service->sendText('08123456789', 'Halo Tes');

        $this->assertTrue($res['success']);
        $http->assertSent(function (Request $request) {
            return $request->url() === 'https://agent.siupk.net/webhook-test/send-message'
                && $request['number'] === '628123456789'
                && $request['text'] === 'Halo Tes'
                && $request['instance'] === 'app-siupk-1';
        });
    }

    public function test_send_bulk_messages_endpoint(): void
    {
        config([
            'services.wa_gateway.base_url' => 'https://agent.siupk.net/webhook-test',
            'services.wa_gateway.api_key' => 'enpii:its.enpii-118',
        ]);

        $http = new HttpFactory;
        $http->fake([
            'https://agent.siupk.net/webhook-test/send-messages' => Http::response(['success' => true], 200),
        ]);

        $service = $this->createService($http, ['whatsapp.is_enabled' => true]);
        $res = $service->sendMessages([
            ['number' => '08123456789', 'text' => 'Msg 1'],
            ['number' => '08987654321', 'text' => 'Msg 2'],
        ]);

        $this->assertTrue($res['success']);
        $this->assertSame(2, $res['count']);
        $http->assertSent(function (Request $request) {
            return $request->url() === 'https://agent.siupk.net/webhook-test/send-messages'
                && count($request['messages']) === 2
                && $request['messages'][0]['number'] === '628123456789';
        });
    }
}
