<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use App\Domain\Sync\Contracts\ExcludedFromDesktopSync;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SitePage extends TenantModel implements ExcludedFromDesktopSync
{
    use HasTenantLocalId;
    use SoftDeletes;

    protected $table = 'site_pages';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->getAttribute('status') === 'published';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
