<?php

namespace App\Providers;

use App\Repositories\Contracts\SuperAdmin\RoleManagementRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\SettingsRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\UserManagementRepositoryInterface;
use App\Repositories\SuperAdmin\RoleManagementRepository;
use App\Repositories\SuperAdmin\SettingsRepository;
use App\Repositories\SuperAdmin\UserManagementRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserManagementRepositoryInterface::class, UserManagementRepository::class);
        $this->app->bind(RoleManagementRepositoryInterface::class, RoleManagementRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
