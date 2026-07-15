<?php

use App\Http\Controllers\Api\Chat\ChatController;
use App\Http\Controllers\Api\Chat\Message\MessageController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'api.error', 'role:clinic_admin|doctor|nurse|accountant|receptionist|staff'])   // ⬅️ جديد
    ->group(function () {
        Route::get('chats/teams', [TeamController::class, 'index']);
        Route::post('chats/teams', [TeamController::class, 'store']);

        Route::prefix('chats')->group(function () {
            Route::get('/', [ChatController::class, 'index']);
            Route::post('/', [ChatController::class, 'store']);
            Route::delete('{chatId}', [ChatController::class, 'destroy']);
            Route::post('{chatId}/participants', [ChatController::class, 'addParticipants']);
            Route::delete('{chatId}/participants/{userId}', [ChatController::class, 'removeParticipant']);

            Route::get('{chatId}/messages', [MessageController::class, 'index']);
            Route::post('messages/send', [MessageController::class, 'store']);
        });

        Broadcast::routes(['middleware' => ['auth:sanctum']]);
    });
