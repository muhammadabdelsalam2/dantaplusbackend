<?php

use App\Http\Controllers\Api\Chat\Message\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'api.error'])
    ->group(function () {

        // get chat messages
        Route::get('/chats/{chatId}/messages', [MessageController::class, 'index']);
        // send message
        Route::post('/messages', [MessageController::class, 'store']);
    });
