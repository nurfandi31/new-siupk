<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Tenancy\TenantContext;
use Enpii\Assistant\Contracts\SessionResolver;
use Illuminate\Http\Request;

/**
 * Resolves the current HTTP request into the assistant session context.
 *
 * - tenant_id  → TenantContext::id() (always available — siupk is multi-tenant)
 * - external_user_id → authenticated user row_id (Auth::user() / impersonated)
 * - persona_slug → read from request input (widget can override)
 */
final class EnpiiSessionResolver implements SessionResolver
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Request $request,
    ) {}

    public function resolve(): array
    {
        $user = $this->request->user();
        $externalUserId = $user !== null
            ? (string) ($user->row_id ?? $user->id ?? '')
            : (string) ($this->request->attributes->get('impersonated_user_id') ?? 'guest');

        return [
            'tenant_id' => (string) $this->tenant->id(),
            'external_user_id' => $externalUserId,
            'persona_slug' => $this->request->input('persona_slug') ?: null,
        ];
    }
}
