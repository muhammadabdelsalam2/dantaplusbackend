<?php

use App\Http\Middleware\ApiErrorMiddleware;
use App\Http\Middleware\RedirectIfAuthenticatedCustom;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use League\Config\Exception\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__ . '/../routes/channels.php',
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

        $middleware->appendToGroup('api/*', [
            ApiErrorMiddleware::class,
            RedirectIfAuthenticatedCustom::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unauthenticated
        $exceptions->render(function (AuthenticationException $e, $request) {
            return ApiResponse::error('Unauthenticated', 401);
        });

        // Validation
        $exceptions->render(function (ValidationException $e, $request) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        });

        // Spatie permission
        $exceptions->render(function (UnauthorizedException $e, $request) {
            return ApiResponse::error($e->getMessage(), 403);
        });

        // Server error
        $exceptions->render(function (Throwable $e, $request) {


            return ApiResponse::error(
                config('app.debug') ? $e->getMessage() : 'Server Error',
                500
            );
        });
    })
    ->create();
