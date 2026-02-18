<?php

use App\Http\Controllers\Api\AuthController;

Route::post('/register/patient', [AuthController::class, 'registerPatient']);

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
