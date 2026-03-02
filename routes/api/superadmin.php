<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Owner\ClinicManagementController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('owner')->middleware(['role:super-admin'])->group(function () {
        Route::get('/clinics', [ClinicManagementController::class, 'index']);
        Route::post('/clinics', [ClinicManagementController::class, 'store']);
        Route::get('/clinics/{clinic}', [ClinicManagementController::class, 'show']);
        Route::post('/clinics/{clinic}', [ClinicManagementController::class, 'update']);
        Route::patch('/clinics/{clinic}/status', [ClinicManagementController::class, 'updateStatus']);
        Route::delete('/clinics/{clinic}', [ClinicManagementController::class, 'destroy']);
        Route::get('/clinics/{clinic}/branches', [ClinicManagementController::class, 'branches']);
         Route::get('/modules', [ClinicManagementController::class, 'clinicmodules']);
    });
});
