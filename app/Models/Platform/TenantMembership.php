<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantMembership extends PlatformModel
{
    protected static function booted(): void
    {
        self::saving(function (self $membership): void {
            $user = User::query()->find($membership->user_id);

            if ($user === null) {
                return;
            }

            if ($user->tenant_id !== null && (int) $user->tenant_id !== (int) $membership->tenant_id) {
                throw new \RuntimeException('A user can belong to only one tenant.');
            }

            if ($user->tenant_id === null) {
                $user->forceFill(['tenant_id' => $membership->tenant_id])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'row_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'row_id');
    }
}
