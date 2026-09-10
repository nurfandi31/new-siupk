<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use App\Domain\Sync\Contracts\ExcludedFromDesktopSync;
use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SitePost extends TenantModel implements ExcludedFromDesktopSync
{
    use HasTenantLocalId;
    use SoftDeletes;

    protected $table = 'site_posts';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->getAttribute('status') === 'published'
            && $this->getAttribute('published_at') !== null
            && $this->getAttribute('published_at')->lte(now());
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
