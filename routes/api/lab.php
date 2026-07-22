<?php

use App\Http\Controllers\Api\Lab\CaseController;
use App\Http\Controllers\Api\Lab\ClinicCaseController;
use App\Http\Controllers\Api\Lab\ClinicController;
use App\Http\Controllers\Api\Lab\ClinicExternalController;
use App\Http\Controllers\Api\Lab\ClinicInviteController;
use App\Http\Controllers\Api\Lab\ClinicPartnershipController;
use App\Http\Controllers\Api\Lab\DeliveryRepController;
use App\Http\Controllers\Api\Lab\DeliveryReportController;
use App\Http\Controllers\Api\Lab\DeliveryTaskController;
use App\Http\Controllers\Api\Lab\LabEquipmentController;
use App\Http\Controllers\Api\Lab\LabSelectController;
use App\Http\Controllers\Api\Lab\LookupController;
use App\Http\Controllers\Api\Lab\MaterialController;
use App\Http\Controllers\Api\Lab\Settings\GalleryController;
use App\Http\Controllers\Api\Lab\Settings\LabProfileController;
use App\Http\Controllers\Api\Lab\Settings\NotificationSettingsController;
use App\Http\Controllers\Api\Lab\Settings\ServiceController;
use App\Http\Controllers\Api\Lab\Settings\UserController;
use App\Http\Controllers\Api\Lab\Settings\WhatsAppSettingsController;
use App\Http\Controllers\Api\Lab\SupportController;
use App\Http\Controllers\Api\Lab\DashboardController;
use App\Http\Controllers\Api\Lab\Accounting\LabAccountingController;
use App\Http\Controllers\Api\Lab\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::get('lab/orders/lab-order/{token}/download', [CaseController::class, 'publicLabOrder'])
    ->name('lab.orders.lab-order.public');

Route::prefix('lab')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::prefix('dashboard')->group(function () {



    Route::get('stats', [DashboardController::class, 'stats']);


    Route::get('charts', [DashboardController::class, 'charts']);
    Route::get('active-cases', [DashboardController::class, 'activeCases']);
});
        Route::middleware(['role:lab_admin|lab_receptionist'])->prefix('accounting')->group(function () {
            Route::get('/summary', [LabAccountingController::class, 'summary']);
            Route::get('/invoices', [LabAccountingController::class, 'invoices']);
            Route::get('/invoices/{invoice}', [LabAccountingController::class, 'showInvoice']);
            Route::get('/invoices/{invoice}/export', [LabAccountingController::class, 'exportInvoice']);
            Route::get('/expenses', [LabAccountingController::class, 'expenses']);
            Route::get('/expense-categories', [LabAccountingController::class, 'categories']);
            Route::get('/technician-earnings', [LabAccountingController::class, 'technicianEarnings']);
            Route::get('/reports/top-paying-clinics', [LabAccountingController::class, 'topPayingClinics']);
            Route::get('/analytics', [LabAccountingController::class, 'analytics']);

            Route::post('/invoices', [LabAccountingController::class, 'storeInvoice']);
            Route::post('/invoices/generate-monthly', [LabAccountingController::class, 'generateMonthlyInvoices']);
            Route::post('/invoices/{invoice}/payments', [LabAccountingController::class, 'recordPayment']);
            Route::post('/invoices/{invoice}/whatsapp', [LabAccountingController::class, 'sendInvoiceWhatsApp']);
            Route::post('/expenses', [LabAccountingController::class, 'storeExpense']);
            Route::post('/expense-categories', [LabAccountingController::class, 'storeCategory']);

            Route::post('/invoices/{invoice}', [LabAccountingController::class, 'updateInvoice']);
            Route::post('/expenses/{expense}', [LabAccountingController::class, 'updateExpense']);
            Route::post('/expense-categories/{category}', [LabAccountingController::class, 'updateCategory']);

            Route::delete('/expenses/{expense}', [LabAccountingController::class, 'deleteExpense']);
            Route::delete('/expense-categories/{category}', [LabAccountingController::class, 'deleteCategory']);
        });
        Route::middleware(['role:lab_admin|lab_receptionist'])->group(function () {
            Route::get('/analytics', [AnalyticsController::class, 'overview']);
        });
        Route::get('/select/{resource}', [LabSelectController::class, 'show']);
        Route::middleware(['role:lab_admin|lab_receptionist'])->prefix('clinics')->group(function () {
            Route::get('/', [ClinicController::class, 'index']);
            Route::post('/invite', [ClinicInviteController::class, 'store']);
            Route::post('/external', [ClinicExternalController::class, 'store']);
            Route::get('/{clinic}', [ClinicController::class, 'show']);
            Route::get('/{clinic}/cases', [ClinicCaseController::class, 'index']);
            Route::delete('/{clinic}/partnership', [ClinicPartnershipController::class, 'destroy']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist'])->prefix('partnered-clinics')->group(function () {
            Route::get('/', [ClinicController::class, 'index']);
            Route::get('/{clinic}', [ClinicController::class, 'show']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist|lab_technician'])->prefix('cases')->group(function () {
            Route::get('/', [CaseController::class, 'index']);
            Route::post('/', [CaseController::class, 'store'])->middleware('role:lab_admin|lab_receptionist');
            Route::get('/{id}', [CaseController::class, 'show']);
            Route::patch('/{id}', [CaseController::class, 'update']);
            Route::patch('/{id}/status', [CaseController::class, 'updateStatus']);
            Route::post('/{id}/assign-technician', [CaseController::class, 'assignTechnician'])
                ->middleware('role:lab_admin|lab_receptionist');
            Route::post('/{caseId}/assign-delivery', [DeliveryTaskController::class, 'assign'])
                ->middleware(['role:lab_admin|lab_receptionist', 'throttle:10,1']);
            Route::get('/{id}/messages', [CaseController::class, 'messages']);
            Route::post('/{id}/messages', [CaseController::class, 'storeMessage']);
            Route::post('/{id}/attachments', [CaseController::class, 'storeAttachment']);
            Route::get('/{id}/activity-log', [CaseController::class, 'activityLog']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist|lab_technician'])->prefix('orders')->group(function () {
            Route::get('/', [CaseController::class, 'index']);
            Route::post('/', [CaseController::class, 'store'])->middleware('role:lab_admin|lab_receptionist');
            Route::get('/{id}', [CaseController::class, 'show']);
            Route::patch('/{id}', [CaseController::class, 'update']);
            Route::patch('/{id}/status', [CaseController::class, 'updateStatus']);
            Route::post('/{id}/assign-technician', [CaseController::class, 'assignTechnician'])
                ->middleware('role:lab_admin|lab_receptionist');
            Route::post('/{id}/complete', [CaseController::class, 'complete'])
                ->middleware('role:lab_admin|lab_receptionist');
            Route::post('/{caseId}/assign-delivery', [DeliveryTaskController::class, 'assign'])
                ->middleware(['role:lab_admin|lab_receptionist', 'throttle:10,1']);
            Route::get('/{id}/notes', [CaseController::class, 'notes']);
            Route::post('/{id}/notes', [CaseController::class, 'storeNote']);
            Route::get('/{id}/messages', [CaseController::class, 'messages']);
            Route::post('/{id}/messages', [CaseController::class, 'storeMessage']);
            Route::post('/{id}/attachments', [CaseController::class, 'storeAttachment']);
            Route::get('/{id}/activity-log', [CaseController::class, 'activityLog']);
            Route::get('/{id}/lab-order', [CaseController::class, 'labOrder']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist|lab_technician'])->group(function () {
            Route::get('/patients', [LookupController::class, 'patients']);
            Route::get('/dentists', [LookupController::class, 'dentists']);
            Route::get('/technicians', [LookupController::class, 'technicians']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist|lab_technician'])->prefix('materials')->group(function () {
            Route::get('/', [MaterialController::class, 'index']);
            Route::get('/low-stock', [MaterialController::class, 'lowStock']);
            Route::get('/expiring', [MaterialController::class, 'expiring']);
            Route::post('/', [MaterialController::class, 'store']);
            Route::get('/{material}', [MaterialController::class, 'show']);
            Route::patch('/{material}', [MaterialController::class, 'update']);
            Route::delete('/{material}', [MaterialController::class, 'destroy']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist|lab_technician|delivery_representative'])->prefix('support')->group(function () {
            Route::get('/tickets', [SupportController::class, 'index']);
            Route::post('/tickets', [SupportController::class, 'store']);
            Route::get('/tickets/{id}', [SupportController::class, 'show']);
            Route::post('/tickets/{id}/messages', [SupportController::class, 'storeMessage']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist'])->prefix('delivery-reps')->group(function () {
            Route::get('/', [DeliveryRepController::class, 'index']);
            Route::post('/', [DeliveryRepController::class, 'store']);
            Route::get('/{id}', [DeliveryRepController::class, 'show']);
            Route::patch('/{id}', [DeliveryRepController::class, 'update']);
            Route::delete('/{id}', [DeliveryRepController::class, 'destroy']);
            Route::get('/{id}/tasks', [DeliveryRepController::class, 'tasks']);
            Route::post('/{id}/login-as', [DeliveryRepController::class, 'loginAs'])
                ->middleware('role:lab_admin');
        });

        Route::middleware(['role:lab_admin'])->prefix('equipments')->group(function () {
            Route::get('/', [LabEquipmentController::class, 'index']);
            Route::post('/', [LabEquipmentController::class, 'store']);
            Route::get('/{id}', [LabEquipmentController::class, 'show']);
            Route::patch('/{id}', [LabEquipmentController::class, 'update']);
            Route::delete('/{id}', [LabEquipmentController::class, 'destroy']);
            Route::post('/{id}/record-maintenance', [LabEquipmentController::class, 'recordMaintenance']);
        });

        Route::middleware(['role:lab_admin'])->prefix('equipment')->group(function () {
            Route::get('/', [LabEquipmentController::class, 'index']);
            Route::post('/', [LabEquipmentController::class, 'store']);
            Route::get('/{id}', [LabEquipmentController::class, 'show']);
            Route::patch('/{id}', [LabEquipmentController::class, 'update']);
            Route::delete('/{id}', [LabEquipmentController::class, 'destroy']);
            Route::post('/{id}/record-maintenance', [LabEquipmentController::class, 'recordMaintenance']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist'])->group(function () {
            Route::get('/delivery-reports', [DeliveryReportController::class, 'index']);
        });

        Route::middleware(['role:lab_admin|lab_receptionist|delivery_representative'])->group(function () {
            Route::get('/delivery-tasks', [DeliveryTaskController::class, 'index']);
            Route::patch('/delivery-tasks/{taskId}/location', [DeliveryTaskController::class, 'updateLocation'])
                ->middleware('throttle:20,1');
            Route::patch('/delivery-tasks/{taskId}/status', [DeliveryTaskController::class, 'updateStatus'])
                ->middleware('throttle:20,1');
            Route::post('/delivery-tasks/{taskId}/confirm-receipt', [DeliveryTaskController::class, 'confirmReceipt']);
        });

        // Lab Settings
        Route::middleware(['role:lab_admin'])->prefix('settings')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::patch('users/{user}', [UserController::class, 'update']);
            Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);

            Route::get('services', [ServiceController::class, 'index']);
            Route::post('services', [ServiceController::class, 'store']);
            Route::patch('services/{service}', [ServiceController::class, 'update']);
            Route::delete('services/{service}', [ServiceController::class, 'destroy']);

            Route::get('profile', [LabProfileController::class, 'show']);
            Route::patch('profile', [LabProfileController::class, 'update']);

            Route::get('gallery', [GalleryController::class, 'index']);
            Route::post('gallery', [GalleryController::class, 'store']);
            Route::delete('gallery/{image}', [GalleryController::class, 'destroy']);

            Route::get('whatsapp', [WhatsAppSettingsController::class, 'show']);
            Route::patch('whatsapp', [WhatsAppSettingsController::class, 'update']);
            Route::post('whatsapp/test', [WhatsAppSettingsController::class, 'test']);
            Route::get('whatsapp/logs', [WhatsAppSettingsController::class, 'logs']);

            Route::get('notifications', [NotificationSettingsController::class, 'show']);
            Route::patch('notifications', [NotificationSettingsController::class, 'update']);
        });
    });

// WhatsApp Webhook — no auth, called by Meta/Twilio directly
Route::match(['get', 'post'], 'lab/api/whatsapp/webhook', [WhatsAppSettingsController::class, 'webhook'])
    ->name('lab.whatsapp.webhook');
