<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommunicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('api.error')->group(function () {

    // Auth
    Route::middleware('guest')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn () => auth()->user());
        Route::get('/user', fn (Request $request) => $request->user());

        Route::prefix('communication')->group(function () {
            Route::get('/contacts', [CommunicationController::class, 'contacts']);
            Route::get('/conversations/{id}/messages', [CommunicationController::class, 'messages']);
            Route::post('/conversations/{id}/messages', [CommunicationController::class, 'storeMessage']);
            Route::patch('/conversations/{id}/status', [CommunicationController::class, 'updateStatus']);
            Route::post('/conversations/{id}/mark-read', [CommunicationController::class, 'markRead']);
        });
    });
});
