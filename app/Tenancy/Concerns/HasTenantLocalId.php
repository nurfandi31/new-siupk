<?php

declare(strict_types=1);

namespace App\Tenancy\Concerns;

use App\Tenancy\Services\TenantSequenceService;
use Illuminate\Database\Eloquent\Model;

trait HasTenantLocalId
{
    public static function bootHasTenantLocalId(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('id') !== null) {
                return;
            }

            $model->setAttribute(
                'id',
                app(TenantSequenceService::class)->next($model->tenantSequenceName()),
            );
        });
    }

    protected function tenantSequenceName(): string
    {
        return $this->getTable();
    }
}
