<?php

use App\Http\Controllers\Api\SuperAdmin\RoleController;
use App\Http\Controllers\Api\SuperAdmin\UserController;

use App\Http\Controllers\Api\SuperAdmin\Settings\BackupSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\BillingPlansController;
use App\Http\Controllers\Api\SuperAdmin\Settings\CustomizationSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\GlobalSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\NotificationSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\PasswordController;
use App\Http\Controllers\Api\SuperAdmin\Settings\ProfileController;
use App\Http\Controllers\Api\SuperAdmin\Settings\UserManagementSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\WhatsappSettingsController;

use Illuminate\Support\Facades\Route;

/**
 * NOTE:
 * - This file is loaded under "/api" prefix already (from bootstrap/app.php).
 * - So routes here will be: /api/superadmin/...
 */

Route::prefix('superadmin')
    ->middleware(['auth:sanctum', 'role:super-admin'])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Users Management
        |--------------------------------------------------------------------------
        */

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);


        /*
        |--------------------------------------------------------------------------
        | Roles Management
        |--------------------------------------------------------------------------
        */

        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::patch('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);


        /*
        |--------------------------------------------------------------------------
        | Settings Module
        |--------------------------------------------------------------------------
        */

        Route::prefix('settings')->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Profile
            |--------------------------------------------------------------------------
            */

            Route::get('/profile', [ProfileController::class, 'show']);
            Route::patch('/profile', [ProfileController::class, 'update']);
            Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);


            /*
            |--------------------------------------------------------------------------
            | Password
            |--------------------------------------------------------------------------
            */

            Route::patch('/password', [PasswordController::class, 'update']);


            /*
            |--------------------------------------------------------------------------
            | Global Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/global', [GlobalSettingsController::class, 'show']);
            Route::patch('/global', [GlobalSettingsController::class, 'update']);


            /*
            |--------------------------------------------------------------------------
            | User Management Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/user-management', [UserManagementSettingsController::class, 'show']);
            Route::patch('/user-management', [UserManagementSettingsController::class, 'update']);


            /*
            |--------------------------------------------------------------------------
            | Notification Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/notifications', [NotificationSettingsController::class, 'show']);
            Route::patch('/notifications', [NotificationSettingsController::class, 'update']);


            /*
            |--------------------------------------------------------------------------
            | Customization Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/customization', [CustomizationSettingsController::class, 'show']);
            Route::patch('/customization', [CustomizationSettingsController::class, 'update']);


            /*
            |--------------------------------------------------------------------------
            | WhatsApp Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/whatsapp', [WhatsappSettingsController::class, 'show']);
            Route::patch('/whatsapp', [WhatsappSettingsController::class, 'update']);
            Route::post('/whatsapp/reconnect', [WhatsappSettingsController::class, 'reconnect']);
            Route::post('/whatsapp/test-message', [WhatsappSettingsController::class, 'testMessage']);
            Route::get('/whatsapp/templates', [WhatsappSettingsController::class, 'listTemplates']);
            Route::put('/whatsapp/templates/{templateKey}', [WhatsappSettingsController::class, 'upsertTemplate']);


            /*
            |--------------------------------------------------------------------------
            | Billing Plans
            |--------------------------------------------------------------------------
            */

            Route::get('/billing/plans', [BillingPlansController::class, 'show']);
            Route::patch('/billing/plans', [BillingPlansController::class, 'update']);


            /*
            |--------------------------------------------------------------------------
            | Backup Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/backup', [BackupSettingsController::class, 'show']);
            Route::patch('/backup', [BackupSettingsController::class, 'update']);
            Route::post('/backup/manual', [BackupSettingsController::class, 'manual']);
        });
    });
