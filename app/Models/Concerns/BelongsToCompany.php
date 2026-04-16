<?php

namespace App\Models\Concerns;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model) {
            if (! $model->company_id && auth()->user()?->company_id) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }

    public function scopeForCurrentCompany(Builder $query): Builder
    {
        if (auth()->user()?->company_id) {
            $query->withoutGlobalScope(CompanyScope::class)
                ->where($this->qualifyColumn('company_id'), auth()->user()->company_id);
        }

        return $query;
    }
}
