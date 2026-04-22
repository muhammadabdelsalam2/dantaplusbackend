<?php

use App\Http\Controllers\Api\Chat\Message\MessageController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\Message;

Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'api.error'])
    ->group(function () {

        // get chat messages
        Route::prefix('chats')->group(function () {
            Route::get('{chatId}/messages', [MessageController::class, 'index']);
            // Send Message 
            Route::post('messages/send', [MessageController::class, 'store']);
            // Get Member Messages
            Route::get('teams', [TeamController::class, 'index']);
        });

        Broadcast::routes([
            'middleware' => ['auth:sanctum'],
        ]);


        Route::post('/test-broadcast', function (Request $request) {

            $message = Message::create([
                'chat_id' => $request->chat_id,
                'user_id' => 1,
                'message' => $request->message,
            ]);

            broadcast(new MessageSent($message));

            return response()->json([
                'status' => true,
                'message' => 'Broadcast sent successfully',
                'data' => $message
            ]);
        });
    });

