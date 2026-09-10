<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'platform';

    protected $table = 'personal_access_tokens';

    protected $guarded = [];
}
