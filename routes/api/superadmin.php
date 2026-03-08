<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Owner\ClinicManagementController;
use App\Http\Controllers\Api\Owner\DentalLabManagementController;
use App\Http\Controllers\Api\Owner\MaterialCommissionController;
use App\Http\Controllers\Api\Owner\MaterialCompanyController;
use App\Http\Controllers\Api\Owner\MaterialOrderController;
use App\Http\Controllers\Api\Owner\MaterialProductController;
use App\Http\Controllers\Api\SuperAdmin\RoleController;
use App\Http\Controllers\Api\SuperAdmin\Settings\BackupSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\BillingPlansController;
use App\Http\Controllers\Api\SuperAdmin\Settings\CustomizationSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\GlobalSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\NotificationSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\PasswordController;
use App\Http\Controllers\Api\SuperAdmin\Settings\ProfileController;
use App\Http\Controllers\Api\SuperAdmin\Settings\UserManagementSettingsController;
use App\Http\Controllers\Api\SuperAdmin\Settings\WhatsappSettingsController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionDashboardController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Super Admin routes Eslam
    Route::prefix('owner')->middleware(['role:super-admin'])->group(function () {
        Route::get('/clinics', [ClinicManagementController::class, 'index']);
        Route::post('/clinics', [ClinicManagementController::class, 'store']);
        Route::get('/clinics/{clinic}', [ClinicManagementController::class, 'show']);
        Route::post('/clinics/{clinic}', [ClinicManagementController::class, 'update']);
        Route::patch('/clinics/{clinic}/status', [ClinicManagementController::class, 'updateStatus']);
        Route::delete('/clinics/{clinic}', [ClinicManagementController::class, 'destroy']);
        Route::get('/clinics/{clinic}/branches', [ClinicManagementController::class, 'branches']);
        Route::get('/modules', [ClinicManagementController::class, 'clinicmodules']);
        Route::get('/labs', [DentalLabManagementController::class, 'index']);
        Route::post('/labs', action: [DentalLabManagementController::class, 'store']);
        Route::post('/labs/bulk-status', [DentalLabManagementController::class, 'bulkStatus']);
        Route::post('/labs/bulk-delete', [DentalLabManagementController::class, 'bulkDelete']);
        Route::get('/labs/{lab}', [DentalLabManagementController::class, 'show']);
        Route::post('/labs/{lab}', [DentalLabManagementController::class, 'update']);
        Route::patch('/labs/{lab}/status', [DentalLabManagementController::class, 'updateStatus']);
        Route::delete('/labs/{lab}', [DentalLabManagementController::class, 'destroy']);

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
    //https://danta.matgary.io
});

/**
 * NOTE:
 * - This file is loaded under "/api" prefix already (from bootstrap/app.php).
 * - So routes here will be: /api/superadmin/...
 */
// Super Admin routes Shady

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
        | Subscription Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/subscriptions/dashboard', [SubscriptionDashboardController::class, 'dashboard']);
        Route::get('/subscriptions', [SubscriptionDashboardController::class, 'index']);

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
