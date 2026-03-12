<?php

use App\Http\Controllers\Api\Lab\SupportController;
use App\Http\Controllers\Api\Lab\CaseController;
use Illuminate\Support\Facades\Route;

Route::prefix('lab')
    ->middleware(['auth:sanctum', 'role:lab'])
    ->group(function () {
        Route::prefix('cases')->group(function () {
            Route::get('/', [CaseController::class, 'index']);
            Route::post('/', [CaseController::class, 'store']);
            Route::get('/{id}', [CaseController::class, 'show']);
            Route::post('/{id}', [CaseController::class, 'update']);
            Route::patch('/{id}/status', [CaseController::class, 'updateStatus']);
            Route::post('/{id}/assign-technician', [CaseController::class, 'assignTechnician']);
            Route::get('/{id}/messages', [CaseController::class, 'messages']);
            Route::post('/{id}/messages', [CaseController::class, 'storeMessage']);
            Route::post('/{id}/attachments', [CaseController::class, 'storeAttachment']);
            Route::get('/{id}/activity-log', [CaseController::class, 'activityLog']);
        });

        Route::prefix('support')->group(function () {
            Route::get('/tickets', [SupportController::class, 'index']);
            Route::post('/tickets', [SupportController::class, 'store']);
            Route::get('/tickets/{id}', [SupportController::class, 'show']);
        });
    });
