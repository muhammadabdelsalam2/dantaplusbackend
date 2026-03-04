<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

<<<<<<< HEAD
Route::middleware('api.error')->group(function () {
=======
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('guest')->group(function () {
>>>>>>> f3eebae6800c910a07686bf2c7a95cffb9c55131

    // Auth
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn () => auth()->user());
        Route::get('/user', fn (Request $request) => $request->user());
    });
});
