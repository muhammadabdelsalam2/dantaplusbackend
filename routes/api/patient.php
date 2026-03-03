<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('patient')->group(function () {
    Route::post('register', [AuthController::class, 'registerPatient']);
    Route::post('verify/account', [AuthController::class, 'verifyAccount'])->name('api.auth.verifyAccount');

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn() => auth()->user());
});





/* 
| ====================================
|  This Is All Api Logic With Patient
/ ====================================
*/
Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
    // Route::get('/patient/profile', ...);
});
