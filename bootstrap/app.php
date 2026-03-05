<?php

use App\Http\Middleware\ApiErrorMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',

        // ✅ Load modular API routes under /api
        then: function () {
            Route::prefix('api')->middleware('api')->group(function () {
                foreach (glob(base_path('routes/api/*.php')) as $file) {
                    require $file;
                }
            });
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ✅ Apply API error handling middleware to ALL api routes
        $middleware->appendToGroup('api', [
            ApiErrorMiddleware::class,
        ]);

        // ✅ Middleware aliases (Spatie Permission)
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            // optional convenience alias
            'api.error' => ApiErrorMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
