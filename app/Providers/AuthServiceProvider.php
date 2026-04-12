<?php

namespace App\Providers;

use App\Models\CaseModel;
use App\Models\DeliveryTask;
use App\Models\User;
use App\Policies\CasePolicy;
use App\Policies\DeliveryTaskPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(DeliveryTask::class, DeliveryTaskPolicy::class);
        Gate::policy(CaseModel::class, CasePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
