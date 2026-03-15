<?php

use App\Http\Controllers\Api\Lab\CaseController;
use App\Http\Controllers\Api\Lab\ClinicCaseController;
use App\Http\Controllers\Api\Lab\ClinicController;
use App\Http\Controllers\Api\Lab\ClinicExternalController;
use App\Http\Controllers\Api\Lab\ClinicInviteController;
use App\Http\Controllers\Api\Lab\ClinicPartnershipController;
use App\Http\Controllers\Api\Lab\MaterialController;
use App\Http\Controllers\Api\Lab\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('lab')
    ->middleware(['auth:sanctum', 'role:lab'])
    ->group(function () {
        Route::prefix('clinics')->group(function () {
            Route::get('/', [ClinicController::class, 'index']);
            Route::post('/invite', [ClinicInviteController::class, 'store']);
            Route::post('/external', [ClinicExternalController::class, 'store']);
            Route::get('/{clinic}', [ClinicController::class, 'show']);
            Route::get('/{clinic}/cases', [ClinicCaseController::class, 'index']);
            Route::delete('/{clinic}/partnership', [ClinicPartnershipController::class, 'destroy']);
        });

        Route::prefix('cases')->group(function () {
            Route::get('/', [CaseController::class, 'index']);
            Route::post('/', [CaseController::class, 'store']);
            Route::get('/{id}', [CaseController::class, 'show']);
            Route::post('/{id}', [CaseController::class, 'update']);
            Route::patch('/{id}/status', [CaseController::class, 'updateStatus']);
            Route::post('/{id}/assign-technician', [CaseController::class, 'assignTechnician']);
            Route::get('/{id}/messages', [CaseController::class, 'messages']);
            Route::post('/{id}/messages', [CaseController::class, 'storeMessage']);
            Route::post('/{id}/attachments', [CaseController::class, 'storeAttachment']);
            Route::get('/{id}/activity-log', [CaseController::class, 'activityLog']);
        });

        Route::prefix('materials')->group(function () {
            Route::get('/', [MaterialController::class, 'index']);
            Route::get('/low-stock', [MaterialController::class, 'lowStock']);
            Route::get('/expiring', [MaterialController::class, 'expiring']);
            Route::post('/', [MaterialController::class, 'store']);
            Route::get('/{material}', [MaterialController::class, 'show']);
            Route::post('/{material}', [MaterialController::class, 'update']);
            Route::delete('/{material}', [MaterialController::class, 'destroy']);
        });

        Route::prefix('support')->group(function () {
            Route::get('/tickets', [SupportController::class, 'index']);
            Route::post('/tickets', [SupportController::class, 'store']);
            Route::get('/tickets/{id}', [SupportController::class, 'show']);
        });
    });
