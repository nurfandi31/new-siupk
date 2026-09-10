<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Access\Services\PermissionChecker;

trait AuthorizesPermission
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $permission = config('permissions.request_map.'.static::class);
        if (! is_string($permission) || $permission === '') {
            return true;
        }

        return app(PermissionChecker::class)->allows($user, $permission);
    }
}
