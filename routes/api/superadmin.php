<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Owner\ClinicManagementController;
use App\Http\Controllers\Api\Owner\MaterialCommissionController;
use App\Http\Controllers\Api\Owner\MaterialCompanyController;
use App\Http\Controllers\Api\Owner\MaterialOrderController;
use App\Http\Controllers\Api\Owner\MaterialProductController;
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

        Route::prefix('material')->group(function () {
            Route::get('/categories', [MaterialProductController::class, 'categories']);

            Route::get('/companies', [MaterialCompanyController::class, 'index']);
            Route::post('/companies', [MaterialCompanyController::class, 'store']);
            Route::get('/companies/{company}', [MaterialCompanyController::class, 'show']);
            Route::post('/companies/{company}', [MaterialCompanyController::class, 'update']);
            Route::patch('/companies/{company}/status', [MaterialCompanyController::class, 'updateStatus']);
            Route::patch('/companies/{company}/commission', [MaterialCompanyController::class, 'updateCommission']);
            Route::delete('/companies/{company}', [MaterialCompanyController::class, 'destroy']);

            Route::get('/companies/{company}/products', [MaterialProductController::class, 'index']);
            Route::post('/companies/{company}/products', [MaterialProductController::class, 'store']);
            Route::post('/products/{product}', [MaterialProductController::class, 'update']);
            Route::patch('/products/{product}/status', [MaterialProductController::class, 'updateStatus']);
            Route::delete('/products/{product}', [MaterialProductController::class, 'destroy']);

            Route::get('/commissions', [MaterialCommissionController::class, 'index']);

            Route::get('/orders', [MaterialOrderController::class, 'index']);
            Route::get('/orders/{order}', [MaterialOrderController::class, 'show']);
        });
    });
});
