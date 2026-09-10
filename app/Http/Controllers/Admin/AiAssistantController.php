<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Enpii\Assistant\AssistantServiceProvider;
use Enpii\Assistant\Models\AuditLog;
use Enpii\Assistant\Models\Conversation;
use Enpii\Assistant\Models\Document;
use Enpii\Assistant\Models\Persona;
use Enpii\Assistant\Models\Tool;
use Enpii\Assistant\Services\Chat\AgentLoop;
use Enpii\Assistant\Services\Rag\DocumentIngestService;
use Enpii\Assistant\Services\SseEmitter;
use Enpii\Assistant\Services\Tools\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AiAssistantController extends Controller
{
    private function qualifiedTable(string $table): string
    {
        $conn = AssistantServiceProvider::$ragConnectionName;

        return ($conn !== null && $conn !== '') ? "{$conn}.{$table}" : $table;
    }

    private function resolveTenantId(Request $request): string
    {
        /** @var TenantContext $tenancy */
        $tenancy = app(TenantContext::class);
        if ($tenancy->isInitialized()) {
            return (string) $tenancy->id();
        }

        if ($request->filled('tenant_id')) {
            return (string) $request->input('tenant_id');
        }

        return '1';
    }

    public function index(ToolRegistry $registry, Request $request): Response
    {
        $personas = Persona::query()
            ->with('tools:id,name')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Persona $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'system_prompt' => $p->system_prompt,
                'is_default' => (bool) $p->is_default,
                'is_active' => (bool) $p->is_active,
                'tools' => $p->tools->map(fn (Tool $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                ]),
                'tools_count' => $p->tools->count(),
                'created_at' => optional($p->created_at)->toIso8601String(),
            ]);

        $registeredNames = array_map(static fn ($h): string => $h->name(), $registry->all());

        $tools = Tool::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tool $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'json_schema' => $t->json_schema,
                'requires_confirmation' => (bool) $t->requires_confirmation,
                'is_active' => (bool) $t->is_active,
                'is_registered' => in_array($t->name, $registeredNames, true),
                'created_at' => optional($t->created_at)->toIso8601String(),
            ]);

        $dbToolNames = $tools->pluck('name')->all();
        $unregisteredHandlers = array_filter(
            $registry->all(),
            static fn ($h) => ! in_array($h->name(), $dbToolNames, true)
        );

        $stats = [
            'total_personas' => Persona::query()->count(),
            'active_personas' => Persona::query()->where('is_active', true)->count(),
            'total_tools' => Tool::query()->count(),
            'active_tools' => Tool::query()->where('is_active', true)->count(),
            'unregistered_code_tools' => count($unregisteredHandlers),
            'total_documents' => Document::query()->count(),
            'total_conversations' => Conversation::query()->count(),
        ];

        return Inertia::render('Admin/AiAssistant/Index', [
            'personas' => $personas,
            'tools' => $tools,
            'stats' => $stats,
        ]);
    }

    public function personas(): JsonResponse
    {
        $personas = Persona::query()
            ->with('tools:id,name')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Persona $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'system_prompt' => $p->system_prompt,
                'is_default' => (bool) $p->is_default,
                'is_active' => (bool) $p->is_active,
                'tools' => $p->tools->map(fn (Tool $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                ]),
                'tools_count' => $p->tools->count(),
                'created_at' => optional($p->created_at)->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'personas' => $personas]);
    }

    public function tools(ToolRegistry $registry): JsonResponse
    {
        $registeredNames = array_map(static fn ($h): string => $h->name(), $registry->all());

        $tools = Tool::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tool $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'json_schema' => $t->json_schema,
                'requires_confirmation' => (bool) $t->requires_confirmation,
                'is_active' => (bool) $t->is_active,
                'is_registered' => in_array($t->name, $registeredNames, true),
                'created_at' => optional($t->created_at)->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'tools' => $tools]);
    }

    public function storePersona(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50'],
            'system_prompt' => ['required', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'tool_ids' => ['nullable', 'array'],
            'tool_ids.*' => ['string'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);
        if (Persona::query()->where('slug', $slug)->exists()) {
            return response()->json(['ok' => false, 'message' => "Persona slug '{$slug}' already exists"], 422);
        }

        if (! empty($data['is_default'])) {
            Persona::query()->where('is_default', true)->update(['is_default' => false]);
        }

        $persona = Persona::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'system_prompt' => $data['system_prompt'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (! empty($data['tool_ids'])) {
            $persona->tools()->sync($data['tool_ids']);
        }

        return response()->json([
            'ok' => true,
            'message' => "Persona '{$persona->name}' berhasil dibuat",
            'persona' => [
                'id' => $persona->id,
                'slug' => $persona->slug,
                'name' => $persona->name,
                'system_prompt' => $persona->system_prompt,
                'is_default' => (bool) $persona->is_default,
                'is_active' => (bool) $persona->is_active,
            ],
        ]);
    }

    public function updatePersona(Request $request, string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'max:50'],
            'system_prompt' => ['sometimes', 'required', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'tool_ids' => ['nullable', 'array'],
            'tool_ids.*' => ['string'],
        ]);

        if (isset($data['slug']) && $data['slug'] !== $persona->slug) {
            if (Persona::query()->where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                return response()->json(['ok' => false, 'message' => "Persona slug '{$data['slug']}' already exists"], 422);
            }
        }

        if (! empty($data['is_default'])) {
            Persona::query()->where('id', '!=', $id)->where('is_default', true)->update(['is_default' => false]);
        }

        $persona->update(array_filter([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'system_prompt' => $data['system_prompt'] ?? null,
            'is_default' => isset($data['is_default']) ? (bool) $data['is_default'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('tool_ids', $data)) {
            $persona->tools()->sync($data['tool_ids'] ?? []);
        }

        return response()->json([
            'ok' => true,
            'message' => "Persona '{$persona->name}' berhasil diperbarui",
            'persona' => [
                'id' => $persona->id,
                'slug' => $persona->slug,
                'name' => $persona->name,
                'system_prompt' => $persona->system_prompt,
                'is_default' => (bool) $persona->is_default,
                'is_active' => (bool) $persona->is_active,
            ],
        ]);
    }

    public function deletePersona(string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);
        $name = $persona->name;
        $persona->delete();

        return response()->json([
            'ok' => true,
            'message' => "Persona '{$name}' berhasil dihapus",
        ]);
    }

    public function togglePersona(string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);
        $persona->is_active = ! $persona->is_active;
        $persona->save();

        return response()->json([
            'ok' => true,
            'is_active' => (bool) $persona->is_active,
            'message' => "Persona '{$persona->name}' ".($persona->is_active ? 'diaktifkan' : 'dinonaktifkan'),
        ]);
    }

    public function syncTools(ToolRegistry $registry): JsonResponse
    {
        $registered = $registry->all();
        $synced = 0;
        $created = 0;

        foreach ($registered as $handler) {
            $tool = Tool::query()->where('name', $handler->name())->first();
            if ($tool) {
                $tool->update([
                    'description' => $handler->description(),
                    'json_schema' => $handler->schema(),
                    'requires_confirmation' => $handler->requiresConfirmation(),
                ]);
                $synced++;
            } else {
                Tool::query()->create([
                    'name' => $handler->name(),
                    'description' => $handler->description(),
                    'json_schema' => $handler->schema(),
                    'requires_confirmation' => $handler->requiresConfirmation(),
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        return response()->json([
            'ok' => true,
            'message' => "Sync selesai: {$created} tool baru ditambahkan, {$synced} tool diperbarui.",
            'created' => $created,
            'synced' => $synced,
            'total_registered' => count($registered),
        ]);
    }

    public function updateTool(Request $request, string $id): JsonResponse
    {
        $tool = Tool::query()->findOrFail($id);

        $data = $request->validate([
            'description' => ['sometimes', 'required', 'string'],
            'requires_confirmation' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tool->update(array_filter([
            'description' => $data['description'] ?? null,
            'requires_confirmation' => isset($data['requires_confirmation']) ? (bool) $data['requires_confirmation'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : null,
        ], fn ($v) => $v !== null));

        return response()->json([
            'ok' => true,
            'message' => "Tool '{$tool->name}' berhasil diperbarui",
            'tool' => [
                'id' => $tool->id,
                'name' => $tool->name,
                'description' => $tool->description,
                'requires_confirmation' => (bool) $tool->requires_confirmation,
                'is_active' => (bool) $tool->is_active,
            ],
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $documents = Document::query()
            ->with(['source:id,tenant_id,type,uri,title,mime_type', 'persona:id,name'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'persona_name' => optional($d->persona)->name ?? 'Global / Semua Persona',
                'source_type' => optional($d->source)->type ?? 'file',
                'source_uri' => optional($d->source)->uri ?? '-',
                'token_count' => $d->token_count,
                'chunks_count' => $d->chunks()->count(),
                'created_at' => optional($d->created_at)->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'documents' => $documents]);
    }

    public function uploadDocument(Request $request, DocumentIngestService $ingest): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,txt,csv,md,json', 'max:20480'],
            'persona_id' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $file = $request->file('file');
        $tenantId = $this->resolveTenantId($request);
        $title = $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $personaId = $request->input('persona_id') ?: null;

        $tempPath = $file->getRealPath();

        try {
            $doc = $ingest->ingestFile(
                tenantId: $tenantId,
                path: $tempPath,
                originalName: $file->getClientOriginalName(),
                mime: $file->getMimeType() ?: 'application/octet-stream',
                personaId: $personaId,
                title: $title
            );

            return response()->json([
                'ok' => true,
                'message' => "Dokumen '{$title}' berhasil diindeks ke Knowledge Base RAG.",
                'document' => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'token_count' => $doc->token_count,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal memproses dokumen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function documentDetail(string $id): JsonResponse
    {
        $doc = Document::query()
            ->with(['source', 'persona:id,name', 'chunks' => fn ($q) => $q->orderBy('chunk_index')->limit(50)])
            ->findOrFail($id);

        return response()->json([
            'ok' => true,
            'document' => [
                'id' => $doc->id,
                'title' => $doc->title,
                'persona_name' => optional($doc->persona)->name ?? 'Global',
                'source' => $doc->source,
                'token_count' => $doc->token_count,
                'created_at' => optional($doc->created_at)->toIso8601String(),
                'chunks' => $doc->chunks->map(fn ($c) => [
                    'index' => $c->chunk_index,
                    'text' => Str::limit($c->text, 300),
                    'token_count' => $c->token_count,
                ]),
            ],
        ]);
    }

    public function deleteDocument(string $id): JsonResponse
    {
        $doc = Document::query()->with('source')->findOrFail($id);
        $source = $doc->source;

        $doc->delete();
        if ($source && $source->documents()->count() === 0) {
            $source->delete();
        }

        return response()->json([
            'ok' => true,
            'message' => 'Dokumen berhasil dihapus dari Knowledge Base.',
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->with('persona:id,name')
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'persona_name' => optional($c->persona)->name ?? 'Default',
                'channel' => $c->channel,
                'status' => $c->status,
                'messages_count' => $c->messages_count,
                'started_at' => optional($c->started_at)->toIso8601String(),
                'last_activity_at' => optional($c->last_activity_at)->toIso8601String(),
            ]);

        return response()->json([
            'ok' => true,
            'count' => $conversations->count(),
            'conversations' => $conversations,
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->actor,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'metadata' => $log->metadata,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'ok' => true,
            'count' => $logs->count(),
            'logs' => $logs,
        ]);
    }

    public function chatStream(Request $request, AgentLoop $loop): StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'persona_slug' => ['nullable', 'string'],
            'conversation_id' => ['nullable', 'uuid'],
            'attachments' => ['nullable', 'array'],
        ]);

        $tenantId = $this->resolveTenantId($request);
        $user = $request->user();
        $actorId = $user ? (string) $user->row_id : '0';

        $personaSlug = $data['persona_slug'] ?? null;
        $persona = $personaSlug
            ? Persona::query()->where('slug', $personaSlug)->where('is_active', true)->first()
            : Persona::query()->where('is_default', true)->where('is_active', true)->first();

        if (! $persona) {
            $persona = Persona::query()->where('is_active', true)->orderBy('id')->first();
        }

        $conversationId = $data['conversation_id'] ?? null;
        $conversation = null;
        if ($conversationId) {
            $conversation = Conversation::query()->find($conversationId);
        }

        if (! $conversation) {
            $conversation = Conversation::query()->create([
                'tenant_id' => $tenantId,
                'external_user_id' => $actorId,
                'persona_id' => optional($persona)->id,
                'channel' => 'web_test',
                'status' => 'active',
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        }

        return response()->stream(function () use ($loop, $tenantId, $actorId, $persona, $conversation, $data): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $sse = new SseEmitter;
            $sse->emit('start', [
                'conversation_id' => $conversation->id,
                'persona' => optional($persona)->name,
            ]);

            try {
                $loop->run(
                    tenantId: $tenantId,
                    externalUserId: $actorId,
                    personaId: optional($persona)->id,
                    conversation: $conversation,
                    userMessage: $data['message'],
                    sse: $sse,
                    attachments: (array) ($data['attachments'] ?? []),
                );

                $sse->emit('done', ['status' => 'completed']);
            } catch (\Throwable $e) {
                $sse->emit('error', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
