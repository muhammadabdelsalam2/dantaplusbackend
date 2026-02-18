<?php

use App\Http\Controllers\Api\AuthController;
Route::post('/register/doctor', [AuthController::class, 'registerDoctor']);


/* 
| ====================================
|  This Is All Api Logic With Doctor
/ ====================================
*/
Route::middleware(['auth:sanctum', 'role:doctor'])->group(function () {
    // Route::get('/doctor/dashboard', ...);
});