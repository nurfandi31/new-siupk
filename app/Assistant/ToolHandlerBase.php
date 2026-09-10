<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Domain\Assistant\Services\AssistantToolService;
use App\Models\User;
use Enpii\Assistant\Contracts\ToolContext;
use Enpii\Assistant\Contracts\ToolHandler;

/**
 * Base class for enpii/assistant tool handlers. Subclasses must implement
 * name()/description()/jsonSchema()/requiresConfirmation() and override
 * invoke() to delegate to AssistantToolService.
 *
 * The actor is read from ToolContext::$actor (typically an Auth user), or
 * falls back to a "service" user when no session is attached.
 */
abstract class ToolHandlerBase implements ToolHandler
{
    public function __construct(
        protected readonly AssistantToolService $tools,
    ) {}

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function jsonSchema(): array;

    public function requiresConfirmation(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    final public function handle(array $params, ToolContext $ctx): array
    {
        $actor = $this->resolveActor($ctx);

        return $this->invoke($params, $actor);
    }

    protected function resolveActor(ToolContext $ctx): User
    {
        return $ctx->actor instanceof User
            ? $ctx->actor
            : $this->resolveSystemActor();
    }

    /**
     * Concrete dispatch to AssistantToolService methods.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    abstract protected function invoke(array $params, User $actor): array;

    private function resolveSystemActor(): User
    {
        $userId = (int) config('assistant.system_actor_user_id', 0);
        if ($userId > 0) {
            $user = User::query()->whereKey($userId)->first();
            if ($user !== null) {
                return $user;
            }
        }

        // Fall back to first superadmin; enpii/assistant never silently fails on missing actor.
        return User::query()
            ->where('is_superadmin', true)
            ->orderBy('row_id')
            ->firstOrFail();
    }
}
