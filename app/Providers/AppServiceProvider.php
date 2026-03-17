<?php

namespace App\Providers;

use App\Repositories\Contracts\SuperAdmin\RoleManagementRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\SettingsRepositoryInterface as SuperAdminSettingsRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\SubscriptionDashboardRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\UserManagementRepositoryInterface;
use App\Repositories\Lab\Clinic\ClinicRepository;
use App\Repositories\Lab\Clinic\ClinicRepositoryInterface;
use App\Repositories\Lab\Settings\ServiceRepository;
use App\Repositories\Lab\Settings\ServiceRepositoryInterface;
use App\Repositories\Lab\Settings\SettingsRepository;
use App\Repositories\Lab\Settings\SettingsRepositoryInterface;
use App\Repositories\Lab\Settings\UserRepository;
use App\Repositories\Lab\Settings\UserRepositoryInterface;
use App\Repositories\SuperAdmin\RoleManagementRepository;
use App\Repositories\SuperAdmin\SettingsRepository as SuperAdminSettingsRepository;
use App\Repositories\SuperAdmin\SubscriptionDashboardRepository;
use App\Repositories\SuperAdmin\UserManagementRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserManagementRepositoryInterface::class, UserManagementRepository::class);
        $this->app->bind(RoleManagementRepositoryInterface::class, RoleManagementRepository::class);
        $this->app->bind(SuperAdminSettingsRepositoryInterface::class, SuperAdminSettingsRepository::class);

        $this->app->bind(
            SubscriptionDashboardRepositoryInterface::class,
            SubscriptionDashboardRepository::class
        );

        $this->app->bind(ClinicRepositoryInterface::class, ClinicRepository::class);

        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
