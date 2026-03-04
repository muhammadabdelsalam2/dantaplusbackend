<?php

<<<<<<< HEAD
use Illuminate\Support\Facades\Route;

Route::prefix('patient')
    ->middleware(['auth:sanctum', 'role:patient'])
    ->group(function () {
        // TODO: patient endpoints
    });
=======
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
>>>>>>> f3eebae6800c910a07686bf2c7a95cffb9c55131
