<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Domain\Sync\Observers\DesktopOutboxObserver;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    use BelongsToTenant;

    protected $connection = 'tenant';

    protected $primaryKey = 'row_id';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::observe(DesktopOutboxObserver::class);
    }

    public function getRowPublicIdentifierAttribute(): string
    {
        return (string) ($this->getAttribute('public_id') ?? $this->getAttribute('id'));
    }
}
