<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\WhatsappPlatformInstance;
use App\Services\PhoneNormalizer;
use App\Services\PlatformWhatsappGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformWhatsappController
{
    public function __construct(
        private readonly PlatformWhatsappGatewayService $gateway,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/PlatformWhatsapp/Index', [
            'instances' => $this->instances(),
            'configured' => $this->gateway->isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        return DB::connection((string) config('tenancy.platform_connection', 'platform'))->transaction(function () use ($data): RedirectResponse {
            $data['is_default'] = WhatsappPlatformInstance::query()->count() === 0;
            $data['instance_name'] = $this->buildInstanceName($data['name']);

            $instance = WhatsappPlatformInstance::query()->create($data);

            return redirect()
                ->route('admin.whatsapp.index')
                ->with('success', 'Instance WhatsApp platform berhasil ditambahkan.')
                ->with('highlight_instance', (string) $instance->row_id);
        });
    }

    public function update(Request $request, WhatsappPlatformInstance $instance): RedirectResponse
    {
        $data = $this->validated($request);

        $instance->update($data);

        return redirect()
            ->route('admin.whatsapp.index')
            ->with('success', 'Instance WhatsApp platform berhasil diperbarui.');
    }

    public function destroy(WhatsappPlatformInstance $instance): RedirectResponse
    {
        $this->gateway->deleteSession($instance->instance_name);
        $instance->delete();

        if ($instance->is_default) {
            $next = WhatsappPlatformInstance::query()->orderBy('row_id')->first();
            $next?->update(['is_default' => true]);
        }

        return redirect()
            ->route('admin.whatsapp.index')
            ->with('success', 'Instance WhatsApp platform berhasil dihapus.');
    }

    public function createSession(WhatsappPlatformInstance $instance): JsonResponse
    {
        return response()->json(
            $this->gateway->createSession($instance->instance_name),
        );
    }

    public function state(WhatsappPlatformInstance $instance): JsonResponse
    {
        return response()->json(
            $this->gateway->connectionState($instance->instance_name),
        );
    }

    public function deleteSession(WhatsappPlatformInstance $instance): JsonResponse
    {
        $result = $this->gateway->deleteSession($instance->instance_name);
        $instance->update(['status' => 'disconnected']);

        return response()->json($result);
    }

    public function test(Request $request, WhatsappPlatformInstance $instance): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->gateway->sendText(
            (string) $validated['phone'],
            (string) ($validated['message'] ?? 'Tes koneksi WhatsApp platform dari siupk.'),
            $instance->instance_name,
        ));
    }

    public function setDefault(WhatsappPlatformInstance $instance): RedirectResponse
    {
        if (! $instance->is_active) {
            return redirect()
                ->route('admin.whatsapp.index')
                ->with('error', 'Instance nonaktif tidak dapat dijadikan default.');
        }

        WhatsappPlatformInstance::query()->update(['is_default' => false]);
        $instance->update(['is_default' => true]);

        return redirect()
            ->route('admin.whatsapp.index')
            ->with('success', 'Instance default berhasil diubah.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $data['phone'] = $data['phone'] === null
            ? null
            : $this->phoneNormalizer->normalize((string) $data['phone']);

        if ($data['phone'] === '') {
            $data['phone'] = null;
        }

        return $data;
    }

    private function buildInstanceName(string $name): string
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $name) ?: 'platform';
        $cleanName = trim($cleanName, '-');
        $base = 'platform-wa-'.$cleanName;
        $instanceName = substr($base, 0, 120);
        $suffix = 1;

        while (WhatsappPlatformInstance::query()->where('instance_name', $instanceName)->exists()) {
            $suffix++;
            $instanceName = substr($base, 0, 116).'-'.$suffix;
        }

        return $instanceName;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instances(): array
    {
        return WhatsappPlatformInstance::query()
            ->orderByDesc('is_default')
            ->orderBy('row_id')
            ->get()
            ->map(fn (WhatsappPlatformInstance $instance): array => [
                'row_id' => (int) $instance->row_id,
                'name' => (string) $instance->name,
                'instance_name' => (string) $instance->instance_name,
                'phone' => $instance->phone !== null ? (string) $instance->phone : null,
                'status' => (string) $instance->status,
                'is_default' => (bool) $instance->is_default,
                'is_active' => (bool) $instance->is_active,
                'daily_limit' => $instance->daily_limit !== null ? (int) $instance->daily_limit : null,
            ])
            ->values()
            ->all();
    }
}
