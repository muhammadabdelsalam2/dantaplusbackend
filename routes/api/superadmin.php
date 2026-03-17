<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Owner\ClinicManagementController;
use App\Http\Controllers\Api\Owner\CommunicationCenterController;
use App\Http\Controllers\Api\Owner\DentalLabManagementController;
use App\Http\Controllers\Api\Owner\EquipmentMaintenanceController;
use App\Http\Controllers\Api\Owner\FeedbackReportController;
use App\Http\Controllers\Api\Owner\MaterialCommissionController;
use App\Http\Controllers\Api\Owner\MaterialCompanyController;
use App\Http\Controllers\Api\Owner\MaterialOrderController;
use App\Http\Controllers\Api\Owner\MaterialProductController;
use App\Http\Controllers\Api\Owner\NotificationCenterController;
use App\Http\Controllers\Api\Owner\NotificationLogController;
use App\Http\Controllers\Api\Owner\RenewalAlertsController;
use App\Http\Controllers\Api\Owner\SupportCenterController;
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
use App\Http\Controllers\Api\SuperAdmin\LabNotificationsController;

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
            Route::delete('/orders/{order}', [MaterialOrderController::class, 'destroy']);
        });

        Route::prefix('maintenance')->group(function () {
            Route::get('/requests', [EquipmentMaintenanceController::class, 'listRequests']);
            Route::post('/requests', [EquipmentMaintenanceController::class, 'storeRequest']);
            Route::patch('/requests/{id}', [EquipmentMaintenanceController::class, 'updateRequest']);
            Route::get('/companies', [EquipmentMaintenanceController::class, 'listCompanies']);
            Route::post('/companies', [EquipmentMaintenanceController::class, 'storeCompany']);
            Route::patch('/alerts/{id}/review', [EquipmentMaintenanceController::class, 'reviewAlert']);
        });

        Route::prefix('communication')->group(function () {
            Route::get('/conversations', [CommunicationCenterController::class, 'index']);
            Route::get('/conversations/{id}/messages', [CommunicationCenterController::class, 'messages']);
            Route::post('/conversations/{id}/messages', [CommunicationCenterController::class, 'storeMessage']);
            Route::patch('/conversations/{id}', [CommunicationCenterController::class, 'update']);
            Route::get('/analytics', [CommunicationCenterController::class, 'analytics']);
        });

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationCenterController::class, 'index']);
            Route::post('/', [NotificationCenterController::class, 'store']);
            Route::post('/test', [NotificationCenterController::class, 'test']);
            Route::patch('/{id}/read', [NotificationCenterController::class, 'markRead']);
        });

        Route::get('/notification-logs', [NotificationLogController::class, 'index']);

        Route::get('/feedback-reports', [FeedbackReportController::class, 'index']);

        Route::get('/support-agents', [SupportCenterController::class, 'agents']);
        Route::prefix('support-tickets')->group(function () {
            Route::get('/', [SupportCenterController::class, 'index']);
            Route::get('/analytics', [SupportCenterController::class, 'analytics']);
            Route::get('/{id}', [SupportCenterController::class, 'show']);
            Route::patch('/{id}', [SupportCenterController::class, 'update']);
            Route::post('/{id}/replies', [SupportCenterController::class, 'storeReply']);
        });

        Route::prefix('alerts')->group(function () {
            Route::get('/renewal', [RenewalAlertsController::class, 'index']);
            Route::post('/renewal/reminders', [RenewalAlertsController::class, 'sendReminder']);
        });
    });
    // https://danta.matgary.io
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
        Route::get('/labs/{lab}/notifications', [LabNotificationsController::class, 'index']);

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
