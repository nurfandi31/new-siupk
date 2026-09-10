<?php

declare(strict_types=1);

namespace App\Tenancy\Concerns;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(app(TenantScope::class));

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if (! $context->isInitialized()) {
                throw new RuntimeException('Cannot create a tenant record without an initialized tenant context.');
            }

            $tenantId = $context->id();

            if ($model->getAttribute('tenant_id') !== null
                && (int) $model->getAttribute('tenant_id') !== $tenantId) {
                throw new RuntimeException('Attempted to create a record for a different tenant.');
            }

            $model->setAttribute('tenant_id', $tenantId);
        });
    }
}
