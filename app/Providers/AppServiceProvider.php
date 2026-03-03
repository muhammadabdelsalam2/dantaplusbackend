<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\SuperAdmin\UserManagementRepositoryInterface;
use App\Repositories\SuperAdmin\UserManagementRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserManagementRepositoryInterface::class, UserManagementRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
