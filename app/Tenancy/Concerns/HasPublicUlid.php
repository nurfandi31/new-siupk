<?php

declare(strict_types=1);

namespace App\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('public_id') === null) {
                $model->setAttribute('public_id', (string) Str::ulid());
            }
        });
    }
}
