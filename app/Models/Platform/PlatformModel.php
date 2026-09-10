<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

abstract class PlatformModel extends Model
{
    protected $connection = 'platform';

    protected $primaryKey = 'row_id';

    protected $guarded = [];
}
