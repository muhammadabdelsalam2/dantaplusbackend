<?php

namespace App\Support\Clinic;

use Illuminate\Database\Eloquent\Builder;

class DentistUserScope
{
    public static function apply(Builder $query): Builder
    {
        return $query->where(function (Builder $nested) {
            $nested
                ->whereIn('role', ['dentist', 'doctor'])
                ->orWhereHas('roles', fn (Builder $role) => $role->whereIn('name', ['dentist', 'doctor']));
        });
    }
}
