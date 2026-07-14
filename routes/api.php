<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\CommunicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::middleware('api.error')->group(function () {

    // Auth
    Route::middleware('guest')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn() => auth()->user());
        Route::get('/me/access', [AccessController::class, 'me']);
        Route::get('/modules/{type}', [AccessController::class, 'modulesForType'])
    ->where('type', 'clinic|lab|supplier|patient|super-admin');
        Route::get('/user', fn(Request $request) => $request->user());
        Route::get('/roles/permissions-matrix', [AccessController::class, 'permissionsMatrix']);
        Route::post('/roles/{roleId}/permissions', [AccessController::class, 'syncRolePermissions'])
    ->where('roleId', '[A-Za-z0-9_\-]+');
        Route::prefix('communication')->group(function () {
            Route::get('/contacts', [CommunicationController::class, 'contacts']);
            Route::get('/conversations/{id}/messages', [CommunicationController::class, 'messages']);
            Route::post('/conversations/{id}/messages', [CommunicationController::class, 'storeMessage']);
            Route::patch('/conversations/{id}/status', [CommunicationController::class, 'updateStatus']);
            Route::post('/conversations/{id}/mark-read', [CommunicationController::class, 'markRead']);
        });
    });
});

Broadcast::routes([
    'middleware' => ['auth:sanctum'],
]);
