<?php

declare(strict_types=1);

namespace App\Tenancy\Contracts;

use App\Models\Platform\DatabaseShard;

interface ShardCredentialProvider
{
    /**
     * @return array{username:string,password:string}
     */
    public function credentialsFor(DatabaseShard $shard): array;
}
