<?php

use App\Http\Middleware\ApiErrorMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->group(function () {
                foreach (glob(base_path('routes/api/*.php')) as $file) {
                    require $file; // ✅ actually load the route files
                }
            });
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    

        $middleware->appendToGroup('api', [
            ApiErrorMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
