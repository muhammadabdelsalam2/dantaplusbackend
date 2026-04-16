<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $user = auth()->user();
        $column = $model->getTable() . '.company_id';

        if ($user && $user->company_id && $model->qualifyColumn('company_id')) {
            $builder->where($column, $user->company_id);
        }
    }
}
