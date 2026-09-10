<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

final class VillageScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user !== null && $user->isVillageUser() && $user->village_row_id !== null) {
            $column = $model->getTable().'.organization_unit_row_id';
            $builder->where($column, (int) $user->village_row_id);
        }
    }
}
