<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Domain\Assistant\Services\AssistantToolService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class AssistantToolController
{
    public function __construct(
        private readonly AssistantToolService $tools,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $tool = (string) $request->input('tool', '');
        if ($tool === '') {
            // Allow path-style: /api/assistant/tools/{tool}
            $tool = (string) $request->route('tool', '');
        }

        if ($tool === '') {
            return response()->json(['error' => 'tool is required'], 400);
        }

        /** @var User $actor */
        $actor = $request->attributes->get('assistant_actor') ?? $request->user();
        if (! $actor instanceof User) {
            return response()->json(['error' => 'actor unresolved'], 401);
        }

        $params = $request->input('params');
        if (! is_array($params)) {
            $params = [];
        }

        try {
            $output = $this->tools->dispatch($tool, $params, $actor);

            return response()->json([
                'ok' => true,
                'tool' => $tool,
                'output' => $output,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'validation_failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (RuntimeException $e) {
            $status = str_starts_with($e->getMessage(), 'Unknown tool') ? 404 : 400;

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => 'tool execution failed',
            ], 500);
        }
    }
}
