<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use App\Domain\Sync\Contracts\ExcludedFromDesktopSync;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Builder;

final class SiteMessage extends TenantModel implements ExcludedFromDesktopSync
{
    protected $table = 'site_messages';

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function isRead(): bool
    {
        return $this->getAttribute('read_at') !== null;
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
