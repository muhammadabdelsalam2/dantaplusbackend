<?php

use App\Http\Controllers\Api\Chat\ChatController;
use App\Http\Controllers\Api\Chat\Message\MessageController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'api.error'])
    ->group(function () {

        // ─── Teams ───────────────────────────────────────────
        Route::get('chats/teams', [TeamController::class, 'index']);
        Route::post('chats/teams', [TeamController::class, 'store']);

        // ─── Chats ───────────────────────────────────────────
        Route::prefix('chats')->group(function () {
            Route::get('/',                                     [ChatController::class, 'index']);
            Route::post('/',                                    [ChatController::class, 'store']);
            Route::delete('{chatId}',                          [ChatController::class, 'destroy']);
            Route::post('{chatId}/participants',               [ChatController::class, 'addParticipants']);
            Route::delete('{chatId}/participants/{userId}',    [ChatController::class, 'removeParticipant']);

            // ─── Messages ─────────────────────────────────────
            Route::get('{chatId}/messages',  [MessageController::class, 'index']);
            Route::post('messages/send',     [MessageController::class, 'store']);
        });

        // ─── Broadcasting ─────────────────────────────────────
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
    });
