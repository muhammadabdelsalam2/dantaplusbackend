<?php

use App\Http\Controllers\Api\Lab\DeliveryRepController;
use App\Http\Controllers\Api\Lab\DeliveryReportController;
use App\Http\Controllers\Api\Lab\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('lab')
    ->middleware(['auth:sanctum', 'role:lab'])
    ->group(function () {
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [SupportController::class, 'index']);
            Route::post('/tickets', [SupportController::class, 'store']);
            Route::get('/tickets/{id}', [SupportController::class, 'show']);
        });

        Route::prefix('delivery-reps')->group(function () {
            Route::get('/', [DeliveryRepController::class, 'index']);
            Route::post('/', [DeliveryRepController::class, 'store']);
            Route::get('/{id}', [DeliveryRepController::class, 'show']);
            Route::post('/{id}', [DeliveryRepController::class, 'update']);
            Route::delete('/{id}', [DeliveryRepController::class, 'destroy']);
        });

        Route::get('/delivery-reports', [DeliveryReportController::class, 'index']);
    });
