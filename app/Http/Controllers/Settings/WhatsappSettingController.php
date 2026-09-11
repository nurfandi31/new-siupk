<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Notifications\Models\WhatsappInstance;
use App\Domain\Notifications\Services\WhatsappNotificationService;
use App\Http\Requests\Settings\WhatsappInstanceRequest;
use App\Services\TenantSettingService;
use App\Services\WhatsappGatewayService;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class WhatsappSettingController
{
    public function __construct(
        private readonly WhatsappGatewayService $gateway,
        private readonly TenantSettingService $settings,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $permissions = app(PermissionChecker::class);
        $canManage = $permissions->allows($request->user(), 'settings.manage');
        $canSend = $permissions->allows($request->user(), 'messages.send');

        if (! $canManage && ! $canSend) {
            abort(403, 'Missing permission: settings.manage or messages.send');
        }

        $payload = [];

        if ($canManage) {
            $payload['instances'] = $this->resolveInstances();
            $payload['global'] = [
                'enabled' => $this->gateway->isEnabled(),
                'configured' => $this->gateway->isConfigured(),
                'rotation_mode' => $this->gateway->getRotationMode(),
                'template_billing' => (string) ($this->settings->get('whatsapp.template_billing', '') ?: ''),
                'template_installment' => (string) ($this->settings->get('whatsapp.template_installment', '') ?: ''),
            ];
            $payload['baseUrl'] = rtrim((string) config('services.wa_gateway.base_url', ''), '/');
        }

        if ($canSend) {
            $payload += $this->resolveBillingPayload($request);
        }

        return Inertia::render('Settings/Whatsapp/Hub', $payload);
    }

    public function store(WhatsappInstanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (WhatsappInstance::query()->count() === 0) {
            $data['is_default'] = true;
        }

        if (! empty($data['is_default'])) {
            WhatsappInstance::query()->update(['is_default' => false]);
        }

        $localId = app(TenantSequenceService::class)->next('whatsapp_instances');
        $data['id'] = $localId;
        $data['instance_name'] = $this->gateway->buildInstanceName($this->context->id().'-'.$localId);

        $instance = WhatsappInstance::query()->create($data);

        return redirect()
            ->route('settings.whatsapp.manage')
            ->with('success', 'Instance WhatsApp baru berhasil ditambahkan.')
            ->with('highlight_instance', (string) $instance->row_id);
    }

    public function update(WhatsappInstanceRequest $request, WhatsappInstance $instance): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['is_default'])) {
            WhatsappInstance::query()->where('row_id', '!=', $instance->row_id)->update(['is_default' => false]);
        }

        $instance->update($data);

        return redirect()
            ->route('settings.whatsapp.manage')
            ->with('success', 'Pengaturan instance WhatsApp berhasil diperbarui.');
    }

    public function destroy(Request $request, WhatsappInstance $instance): RedirectResponse
    {
        $this->authorizeSettings($request);

        $this->gateway->deleteInstance($instance->instance_name);
        $instance->delete();

        if ($instance->is_default) {
            $next = WhatsappInstance::query()->orderBy('row_id')->first();
            if ($next !== null) {
                $next->update(['is_default' => true]);
            }
        }

        return redirect()
            ->route('settings.whatsapp.manage')
            ->with('success', 'Instance WhatsApp berhasil dihapus.');
    }

    public function createSession(WhatsappInstance $instance): JsonResponse
    {
        return response()->json(
            $this->gateway->createInstance($this->context->id(), $instance->instance_name)
        );
    }

    public function state(WhatsappInstance $instance): JsonResponse
    {
        return response()->json(
            $this->gateway->connectionState($instance->instance_name)
        );
    }

    public function deleteSession(WhatsappInstance $instance): JsonResponse
    {
        $result = $this->gateway->deleteInstance($instance->instance_name);
        $instance->update(['status' => 'close']);

        return response()->json($result);
    }

    public function test(Request $request, WhatsappInstance $instance): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->gateway->sendText(
            $validated['phone'],
            $validated['message'] ?: 'Tes koneksi WhatsApp instance '.$instance->name.' dari siupk.',
            $instance->instance_name,
        ));
    }

    public function updateGlobal(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);

        $validated = $request->validate([
            'template_billing' => ['nullable', 'string', 'max:2000'],
            'template_installment' => ['nullable', 'string', 'max:2000'],
            'is_enabled' => ['nullable', 'boolean'],
            'rotation_mode' => ['nullable', 'string', 'in:round_robin,default_only'],
        ]);

        $this->settings->set('whatsapp.template_billing', $validated['template_billing'] ?? '');
        $this->settings->set('whatsapp.template_installment', $validated['template_installment'] ?? '');
        $this->gateway->setEnabled((bool) ($validated['is_enabled'] ?? false));

        if (array_key_exists('rotation_mode', $validated) && is_string($validated['rotation_mode'])) {
            $this->gateway->setRotationMode($validated['rotation_mode']);
        }

        return redirect()
            ->route('settings.whatsapp.hub')
            ->with('success', 'Pengaturan WhatsApp berhasil disimpan.');
    }

    private function authorizeSettings(Request $request): void
    {
        app(PermissionChecker::class)->denyUnless($request->user(), 'settings.manage');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveInstances(): array
    {
        if (! $this->instancesTableExists()) {
            return [];
        }

        return WhatsappInstance::query()
            ->orderByDesc('is_default')
            ->orderBy('row_id')
            ->get()
            ->map(fn (WhatsappInstance $instance): array => [
                'row_id' => (int) $instance->row_id,
                'id' => (int) $instance->id,
                'name' => (string) $instance->name,
                'instance_name' => (string) $instance->instance_name,
                'phone_number' => $instance->phone_number !== null ? (string) $instance->phone_number : null,
                'status' => (string) $instance->status,
                'is_default' => (bool) $instance->is_default,
                'is_active' => (bool) $instance->is_active,
                'daily_limit' => (int) $instance->daily_limit,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBillingPayload(Request $request): array
    {
        $due = $this->resolveDueDate($request);
        $items = app(WhatsappNotificationService::class)->dueOn($due);
        $state = $this->gateway->isConfigured()
            ? $this->gateway->connectionState()
            : [
                'success' => false,
                'status' => 'unconfigured',
                'message' => 'Gateway WhatsApp belum dikonfigurasi.',
                'state' => null,
                'instance' => $this->gateway->getInstance(),
            ];

        return [
            'due_date' => $due->toDateString(),
            'items' => $items,
            'billing_gateway' => [
                'enabled' => $this->gateway->isEnabled(),
                'configured' => $this->gateway->isConfigured(),
                'instance' => $this->gateway->getInstance(),
                'state' => $state['state'] ?? null,
                'status_message' => $state['message'] ?? null,
            ],
            'totals' => [
                'count' => count($items),
                'amount' => array_sum(array_column($items, 'amount')),
                'with_phone' => count(array_filter($items, fn (array $item): bool => $item['can_send'])),
            ],
        ];
    }

    private function resolveDueDate(Request $request): CarbonImmutable
    {
        $raw = $request->query('due_date');
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::today();
    }

    private function instancesTableExists(): bool
    {
        try {
            return DB::connection('tenant')->getSchemaBuilder()->hasTable('whatsapp_instances');
        } catch (\Throwable) {
            return false;
        }
    }
}
