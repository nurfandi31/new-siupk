<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WhatsappGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for WhatsApp Gateway endpoints.
 *
 * @see F:\Workspace\laragon\www\siupk\WA-GATEWAY-API.md
 */
final class WhatsappController extends Controller
{
    public function __construct(
        private readonly WhatsappGatewayService $gateway,
    ) {}

    /**
     * Endpoint 1: Create Instance
     * POST /pengaturan/whatsapp/save_device
     */
    public function createInstance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lokasi' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->gateway->createInstance($validated['lokasi'] ?? null);

        return response()->json($result);
    }

    /**
     * Endpoint 2: Get Instance State (polling)
     * GET /pengaturan/whatsapp/instance_state
     * GET /wa/instance-state
     */
    public function instanceState(Request $request): JsonResponse
    {
        $result = $this->gateway->connectionState();

        return response()->json($result);
    }

    /**
     * Endpoint 3: Delete Instance
     * POST /pengaturan/whatsapp/delete_session
     */
    public function deleteInstance(Request $request): JsonResponse
    {
        $result = $this->gateway->deleteInstance();

        return response()->json($result);
    }

    /**
     * Endpoint 4: Send Single Message
     * POST /wa/send
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:20'],
            'text' => ['required', 'string', 'max:2000'],
            'instance' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->gateway->sendText(
            $validated['number'],
            $validated['text'],
            $validated['instance'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Endpoint 5: Send Bulk Messages
     * POST /wa/send-bulk
     */
    public function sendMessages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instance' => ['nullable', 'string', 'max:100'],
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.number' => ['required', 'string', 'max:20'],
            'messages.*.text' => ['required', 'string', 'max:2000'],
        ]);

        $result = $this->gateway->sendMessages(
            $validated['messages'],
            $validated['instance'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Endpoint 6: Get History Messages
     * GET /wa/history
     */
    public function historyMessage(Request $request): JsonResponse
    {
        $instance = $request->query('instance');
        $instanceStr = is_string($instance) && $instance !== '' ? $instance : null;

        $result = $this->gateway->historyMessage($instanceStr);

        return response()->json($result);
    }
}
