<?php

use App\Http\Controllers\Api\Lab\CaseController;

use App\Http\Controllers\Api\Lab\ClinicCaseController;
use App\Http\Controllers\Api\Lab\ClinicController;
use App\Http\Controllers\Api\Lab\ClinicExternalController;
use App\Http\Controllers\Api\Lab\ClinicInviteController;
use App\Http\Controllers\Api\Lab\ClinicPartnershipController;
use App\Http\Controllers\Api\Lab\MaterialController;

use App\Http\Controllers\Api\Lab\DeliveryRepController;
use App\Http\Controllers\Api\Lab\DeliveryReportController;
use App\Http\Controllers\Api\Lab\LabEquipmentController;
use App\Http\Controllers\Api\Lab\Settings\GalleryController;
use App\Http\Controllers\Api\Lab\Settings\LabProfileController;
use App\Http\Controllers\Api\Lab\Settings\NotificationSettingsController;
use App\Http\Controllers\Api\Lab\Settings\ServiceController;
use App\Http\Controllers\Api\Lab\Settings\UserController;
use App\Http\Controllers\Api\Lab\Settings\WhatsAppSettingsController;
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

        Route::prefix('delivery-reps')->group(function () {
            Route::get('/', [DeliveryRepController::class, 'index']);
            Route::post('/', [DeliveryRepController::class, 'store']);
            Route::get('/{id}', [DeliveryRepController::class, 'show']);
            Route::post('/{id}', [DeliveryRepController::class, 'update']);
            Route::delete('/{id}', [DeliveryRepController::class, 'destroy']);
        });

        Route::prefix('equipments')->group(function () {
            Route::get('/', [LabEquipmentController::class, 'index']);
            Route::post('/', [LabEquipmentController::class, 'store']);
            Route::get('/{id}', [LabEquipmentController::class, 'show']);
            Route::post('/{id}', [LabEquipmentController::class, 'update']);
            Route::delete('/{id}', [LabEquipmentController::class, 'destroy']);
            Route::post('/{id}/record-maintenance', [LabEquipmentController::class, 'recordMaintenance']);
        });

        Route::get('/delivery-reports', [DeliveryReportController::class, 'index']);

        // Lab Settings
        Route::prefix('settings')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::post('users/{user}', [UserController::class, 'update']);
            Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);

            Route::get('services', [ServiceController::class, 'index']);
            Route::post('services', [ServiceController::class, 'store']);
            Route::post('services/{service}', [ServiceController::class, 'update']);
            Route::delete('services/{service}', [ServiceController::class, 'destroy']);

            Route::get('profile', [LabProfileController::class, 'show']);
            Route::post('profile', [LabProfileController::class, 'update']);

            Route::get('gallery', [GalleryController::class, 'index']);
            Route::post('gallery', [GalleryController::class, 'store']);
            Route::delete('gallery/{image}', [GalleryController::class, 'destroy']);

            Route::get('whatsapp', [WhatsAppSettingsController::class, 'show']);
            Route::post('whatsapp', [WhatsAppSettingsController::class, 'update']);
            Route::post('whatsapp/test', [WhatsAppSettingsController::class, 'test']);
            Route::get('whatsapp/logs', [WhatsAppSettingsController::class, 'logs']);

            Route::get('notifications', [NotificationSettingsController::class, 'show']);
            Route::post('notifications', [NotificationSettingsController::class, 'update']);
        });
    });

// WhatsApp Webhook — no auth, called by Meta/Twilio directly
Route::match(['get', 'post'], 'lab/api/whatsapp/webhook', [WhatsAppSettingsController::class, 'webhook'])
    ->name('lab.whatsapp.webhook');
