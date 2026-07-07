<?php

use App\Http\Controllers\Api\Clinic\AppointmentController;
use App\Http\Controllers\Api\Clinic\BillingController;
use App\Http\Controllers\Api\Clinic\CartController;
use App\Http\Controllers\Api\Clinic\ClinicController;
use App\Http\Controllers\Api\Clinic\CommunicationController;
use App\Http\Controllers\Api\Clinic\DentalLabController;
use App\Http\Controllers\Api\Clinic\EquipmentController;
use App\Http\Controllers\Api\Clinic\InventoryController;
use App\Http\Controllers\Api\Clinic\MaterialController;
use App\Http\Controllers\Api\Clinic\MessageController;
use App\Http\Controllers\Api\Clinic\NotificationCenterController;
use App\Http\Controllers\Api\Clinic\OrderController;
use App\Http\Controllers\Api\Clinic\ProcurementController;
use App\Http\Controllers\Api\Clinic\WhatsappBotController;
use App\Http\Controllers\Api\Clinic\Insurance\InsuranceClaimController;
use App\Http\Controllers\Api\Clinic\Insurance\InsuranceCompanyController;
use App\Http\Controllers\Api\Clinic\PatientController;
use App\Http\Controllers\Api\Clinic\SelectController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicDoctorReminderSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicFeedbackSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicAppointmentSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicAppearanceSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicCommunicationSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\BranchController as SettingsBranchController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicFinancialSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicInfoController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicInsurancePriceListController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicIntegrationSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicQueueNotificationSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicReminderSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicSecuritySettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicServicePricingController;
use App\Http\Controllers\Api\Clinic\Settings\DentistController as SettingsDentistController;
use App\Http\Controllers\Api\Clinic\Settings\GeneralSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Api\Clinic\TreatmentController;
use App\Http\Controllers\Api\Clinic\TaskController;
use App\Http\Controllers\Api\Clinic\UserController;
use App\Http\Controllers\Api\Owner\SupportCenterController;
use Illuminate\Support\Facades\Route;

Route::prefix('clinic')
    ->middleware(['api.error', 'auth:sanctum', 'role:clinic_admin|doctor|nurse|accountant|receptionist|staff'])
    ->group(function () {

        // ─── Patient Messaging ───────────────────────────────────────────────
        Route::prefix('messages')
            ->group(function () {
                Route::post('/patients/filter',  [MessageController::class, 'filterPatients']);
                Route::post('/send',             [MessageController::class, 'send']);
                Route::post('/history',          [MessageController::class, 'history']);
                Route::post('/templates',        [MessageController::class, 'templates']);
                Route::post('/templates/store',  [MessageController::class, 'storeTemplate']);
            });

        Route::middleware('permission:communication.send')
            ->prefix('communication/conversations')
            ->group(function () {
                Route::post('/list',     [CommunicationController::class, 'list']);
                Route::post('/messages', [CommunicationController::class, 'messages']);
                Route::post('/send',     [CommunicationController::class, 'send']);
                Route::post('/read',     [CommunicationController::class, 'read']);
            });

        // ─── Settings ────────────────────────────────────────────────────────
        Route::prefix('settings')->group(function () {
            Route::get('/profile',  [SettingsProfileController::class, 'show']);
            Route::post('/profile', [SettingsProfileController::class, 'update']);
            Route::post('/profile/password', [SettingsProfileController::class, 'updatePassword']);

            Route::middleware('permission:settings.manage')->group(function () {
                Route::get('/general',  [GeneralSettingsController::class, 'show']);
                Route::post('/general', [GeneralSettingsController::class, 'update']);

                Route::get('/financial',  [ClinicFinancialSettingsController::class, 'show']);
                Route::post('/financial', [ClinicFinancialSettingsController::class, 'update']);

                Route::get('/appointments',  [ClinicAppointmentSettingsController::class, 'show']);
                Route::post('/appointments', [ClinicAppointmentSettingsController::class, 'update']);

                Route::get('/reminders',   [ClinicReminderSettingsController::class, 'show']);
                Route::post('/reminders', [ClinicReminderSettingsController::class, 'update']);

                Route::get('/doctor-reminders',         [ClinicDoctorReminderSettingsController::class, 'show']);
                Route::post('/doctor-reminders',       [ClinicDoctorReminderSettingsController::class, 'update']);
                Route::post('/doctor-reminders/trigger',[ClinicDoctorReminderSettingsController::class, 'trigger']);
                Route::get('/doctor-reminders/logs',    [ClinicDoctorReminderSettingsController::class, 'logs']);

                Route::get('/queue-notifications',    [ClinicQueueNotificationSettingsController::class, 'show']);
                Route::post('/queue-notifications',  [ClinicQueueNotificationSettingsController::class, 'update']);
                Route::post('/queue-notifications/test', [ClinicQueueNotificationSettingsController::class, 'test']);

                Route::get('/feedback',      [ClinicFeedbackSettingsController::class, 'show']);
                Route::post('/feedback',    [ClinicFeedbackSettingsController::class, 'update']);
                Route::get('/feedback/logs', [ClinicFeedbackSettingsController::class, 'logs']);

                Route::get('/security',    [ClinicSecuritySettingsController::class, 'show']);
                Route::post('/security',  [ClinicSecuritySettingsController::class, 'update']);
                Route::post('/security/backup', [ClinicSecuritySettingsController::class, 'backup']);

                Route::get('/appearance',   [ClinicAppearanceSettingsController::class, 'show']);
                Route::post('/appearance', [ClinicAppearanceSettingsController::class, 'update']);

                Route::get('/integrations',                      [ClinicIntegrationSettingsController::class, 'show']);
                Route::post('/integrations/connect/google',      [ClinicIntegrationSettingsController::class, 'connectGoogle']);
                Route::post('/integrations/connect/outlook',     [ClinicIntegrationSettingsController::class, 'connectOutlook']);

                Route::get('/communication',                        [ClinicCommunicationSettingsController::class, 'show']);
                Route::post('/communication/whatsapp',              [ClinicCommunicationSettingsController::class, 'updateWhatsApp']);
                Route::post('/communication/sms-email',             [ClinicCommunicationSettingsController::class, 'updateSmsEmail']);
                Route::post('/communication/test-connection',       [ClinicCommunicationSettingsController::class, 'testConnection']);
                Route::get('/communication/webhook-url',            [ClinicCommunicationSettingsController::class, 'webhookUrl']);
                Route::post('/communication/templates/{id}',        [ClinicCommunicationSettingsController::class, 'updateTemplate']);

                Route::get('/service-pricing',           [ClinicServicePricingController::class, 'index']);
                Route::post('/service-pricing',          [ClinicServicePricingController::class, 'store']);
                Route::post('/service-pricing/{serviceId}', [ClinicServicePricingController::class, 'update']);
                Route::delete('/service-pricing/{id}',   [ClinicServicePricingController::class, 'destroy']);

                Route::get('/clinic-info',  [ClinicInfoController::class, 'show']);
                Route::post('/clinic-info', [ClinicInfoController::class, 'update']);

                Route::get('/branches',        [SettingsBranchController::class, 'index']);
                Route::post('/branches',       [SettingsBranchController::class, 'store']);
                Route::get('/branches/{id}',   [SettingsBranchController::class, 'show']);
                Route::post('/branches/{id}',  [SettingsBranchController::class, 'update']);
                Route::delete('/branches/{id}',[SettingsBranchController::class, 'destroy']);

                Route::get('/dentists',        [SettingsDentistController::class, 'index']);
                Route::post('/dentists',       [SettingsDentistController::class, 'store']);
                Route::get('/dentists/{id}',   [SettingsDentistController::class, 'show']);
                Route::post('/dentists/{id}', [SettingsDentistController::class, 'update']);
                Route::delete('/dentists/{id}',[SettingsDentistController::class, 'destroy']);
            });
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/insurance-price-lists',          [ClinicInsurancePriceListController::class, 'index']);
            Route::post('/insurance-price-lists',         [ClinicInsurancePriceListController::class, 'store']);
            Route::post('/insurance-price-lists/import',  [ClinicInsurancePriceListController::class, 'import']);
            Route::post('/insurance-price-lists/{id}',   [ClinicInsurancePriceListController::class, 'update']);
            Route::delete('/insurance-price-lists/{id}',  [ClinicInsurancePriceListController::class, 'destroy']);
            Route::post('/reminders/trigger', [ClinicReminderSettingsController::class, 'trigger']);
            Route::get('/reminders/logs',     [ClinicReminderSettingsController::class, 'logs']);
        });

        // ─── Insurance ───────────────────────────────────────────────────────
        Route::prefix('insurance')->group(function () {
            Route::middleware('permission:insurance.view|patients.view')->group(function () {
                Route::get('/companies',    [InsuranceCompanyController::class, 'index']);
                Route::get('/companies/{id}', [InsuranceCompanyController::class, 'show']);
                Route::get('/companies/{id}/price-list-items', [InsuranceCompanyController::class, 'priceListItems']);
                Route::get('/patients/lookup',    [InsuranceClaimController::class, 'patientLookup']);
                Route::get('/analytics',    [InsuranceClaimController::class, 'analytics']);
                Route::get('/monthly',    [InsuranceClaimController::class, 'monthly']);
                Route::get('/approval-report',    [InsuranceClaimController::class, 'approvalReport']);
                Route::get('/claims',       [InsuranceClaimController::class, 'index']);
                Route::get('/claims/{id}',  [InsuranceClaimController::class, 'show']);
            });
            Route::middleware('permission:insurance.create')->group(function () {
                Route::post('/companies', [InsuranceCompanyController::class, 'store']);
                Route::post('/claims',    [InsuranceClaimController::class, 'store']);
            });
            Route::middleware('permission:insurance.update')->group(function () {
                Route::post('/companies/{id}', [InsuranceCompanyController::class, 'update']);
                Route::post('/claims/{id}',    [InsuranceClaimController::class, 'update']);
                Route::post('/claims/{id}/patient-consent', [InsuranceClaimController::class, 'uploadConsent']);
            });
            Route::middleware('permission:insurance.delete')->group(function () {
                Route::delete('/companies/{id}', [InsuranceCompanyController::class, 'destroy']);
                Route::delete('/claims/{id}',    [InsuranceClaimController::class, 'destroy']);
            });
        });

        // ─── Admin-only ──────────────────────────────────────────────────────
        Route::middleware('role:clinic_admin')->group(function () {
            Route::get('/my-clinic',          [ClinicController::class, 'getMyClinic']);
            Route::post('/update-my-clinic',  [ClinicController::class, 'updateMyClinic']);
            Route::get('/users',              [UserController::class, 'index']);
            Route::post('/users',             [UserController::class, 'store']);
            Route::get('/users/{id}',         [UserController::class, 'show']);
            Route::post('/users/{id}',       [UserController::class, 'update']);
            Route::delete('/users/{id}',      [UserController::class, 'destroy']);

            Route::prefix('whatsapp-bot')->group(function () {
                Route::get('/', [WhatsappBotController::class, 'index']);
                Route::post('/update', [WhatsappBotController::class, 'update']);
                Route::post('/toggle', [WhatsappBotController::class, 'toggle']);
                Route::post('/simulate', [WhatsappBotController::class, 'simulate']);
            });
        });

        // ─── Patients ────────────────────────────────────────────────────────
        Route::middleware('permission:patients.view')->get('/patients',                           [PatientController::class, 'index']);
        Route::middleware('permission:patients.create')->post('/patients',                        [PatientController::class, 'store']);
        Route::middleware('permission:patients.view')->get('/patients/{id}',                      [PatientController::class, 'show']);
        Route::middleware('permission:patients.update')->post('/patients/{id}',                  [PatientController::class, 'update']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/dental-chart',         [PatientController::class, 'dentalChart']);
        Route::middleware('permission:patients.update')->post('/patients/{id}/dental-chart',      [PatientController::class, 'storeDentalChart']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/radiology',            [PatientController::class, 'radiology']);
        Route::middleware('permission:patients.update')->post('/patients/{id}/radiology/upload',  [PatientController::class, 'uploadRadiology']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/labs',                 [PatientController::class, 'labCases']);
        Route::middleware('permission:labs.send')->post('/patients/{id}/labs',                    [PatientController::class, 'sendLabCase']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/discussion',           [PatientController::class, 'discussion']);
        Route::middleware('permission:communication.send')->post('/patients/{id}/discussion',     [PatientController::class, 'storeDiscussion']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/analytics',            [PatientController::class, 'analytics']);

        // ─── Appointments ────────────────────────────────────────────────────
        Route::middleware('permission:appointments.view')->get('/appointments',       [AppointmentController::class, 'index']);
        Route::middleware('permission:appointments.create')->post('/appointments',    [AppointmentController::class, 'store']);
        Route::middleware('permission:appointments.update')->post('/appointments/{id}', [AppointmentController::class, 'update']);
        Route::middleware('permission:appointments.view')->get('/appointments/{id}',  [AppointmentController::class, 'show']);

        Route::post('/appointments/{id}/approve', [AppointmentController::class, 'approve']);

        // ─── Notifications ───────────────────────────────────────────────────
        Route::get('/notifications',                     [NotificationCenterController::class, 'index']);
        Route::get('/notifications/unread',              [NotificationCenterController::class, 'unread']);
        Route::post('/notifications/{id}/read',         [NotificationCenterController::class, 'markRead']);
        Route::post('/notifications/mark-all-read',      [NotificationCenterController::class, 'markAllRead']);

        // ─── Treatments ──────────────────────────────────────────────────────
        Route::middleware('permission:treatments.manage')->get('/treatments',       [TreatmentController::class, 'index']);
        Route::middleware('permission:treatments.manage')->post('/treatments',      [TreatmentController::class, 'store']);
        Route::middleware('permission:treatments.manage')->get('/treatments/{id}',  [TreatmentController::class, 'show']);

        Route::get('/select/{resource}', [SelectController::class, 'show']);

        // ─── Materials ───────────────────────────────────────────────────────
        Route::middleware('permission:materials.view')->prefix('materials')->group(function () {
            Route::get('/',         [MaterialController::class, 'index']);
            Route::get('/filters',  [MaterialController::class, 'filters']);
            Route::get('/{material}', [MaterialController::class, 'show']);
        });

        // ─── Inventory ───────────────────────────────────────────────────────
        Route::middleware('permission:inventory.view')->prefix('inventory')->group(function () {
            Route::get('/',          [InventoryController::class, 'index']);
            Route::get('/{inventory}', [InventoryController::class, 'show']);
            Route::post('/scan',     [InventoryController::class, 'scan']);
        });
        Route::middleware('permission:inventory.manage')->prefix('inventory')->group(function () {
            Route::post('/',            [InventoryController::class, 'store']);
            Route::post('/{inventory}',[InventoryController::class, 'update']);
            Route::delete('/{inventory}',[InventoryController::class, 'destroy']);
        });

        // ─── Procurement ─────────────────────────────────────────────────────
        Route::middleware('permission:inventory.manage')->prefix('procurement')->group(function () {
            Route::get('/',             [ProcurementController::class, 'index']);
            Route::post('/{po}/approve',[ProcurementController::class, 'approve']);
            Route::post('/{po}/receive',[ProcurementController::class, 'receive']);
            Route::delete('/{po}',      [ProcurementController::class, 'cancel']);
        });

        // ─── Orders ──────────────────────────────────────────────────────────
        Route::middleware('permission:orders.view')->prefix('orders')->group(function () {
            Route::get('/',       [OrderController::class, 'index']);
            Route::get('/{order}',[OrderController::class, 'show']);
        });
        Route::middleware('permission:orders.manage')->prefix('orders')->group(function () {
            Route::post('/{order}/approve-changes', [OrderController::class, 'approveChanges']);
            Route::post('/{order}/reject-changes',  [OrderController::class, 'rejectChanges']);
            Route::post('/{order}/pay',             [OrderController::class, 'pay']);
            Route::post('/{order}/restock',          [OrderController::class, 'restock']);
        });

        Route::middleware('permission:orders.manage')->prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'show']);
            Route::post('/items', [CartController::class, 'storeItem']);
            Route::delete('/items/{id}', [CartController::class, 'destroyItem']);
            Route::post('/checkout', [CartController::class, 'checkout']);
        });

        // ─── Equipment ───────────────────────────────────────────────────────
        Route::middleware('permission:equipment.view')->prefix('equipment')->group(function () {
            Route::get('/', [EquipmentController::class, 'index']);
            Route::post('/{equipment}/report', [EquipmentController::class, 'report']);
            Route::post('/', [EquipmentController::class, 'store']);
        });
        Route::middleware('permission:equipment.view')->post('/equipment-reports/{id}/assign-company', [EquipmentController::class, 'assignCompany']);

        // ─── Tasks ───────────────────────────────────────────────────────────
        Route::middleware('permission:tasks.view')->get('/tasks',              [TaskController::class, 'index']);
        Route::middleware('permission:tasks.manage')->post('/tasks',            [TaskController::class, 'store']);
        Route::middleware('permission:tasks.manage')->post('/tasks/{id}',      [TaskController::class, 'update']);
        Route::middleware('permission:tasks.manage')->delete('/tasks/{id}',     [TaskController::class, 'destroy']);

        // ─── Dental Labs ─────────────────────────────────────────────────────
        Route::middleware('permission:dental_labs.view')->get('/dental-labs/analytics',  [DentalLabController::class, 'analytics']);
        Route::middleware('permission:dental_labs.view')->get('/dental-labs',            [DentalLabController::class, 'index']);
        Route::middleware('permission:dental_labs.manage')->post('/dental-labs',         [DentalLabController::class, 'store']);
        Route::middleware('permission:dental_labs.view')->get('/dental-labs/{id}',       [DentalLabController::class, 'show']);
        Route::middleware('permission:dental_labs.manage')->post('/dental-labs/{id}',   [DentalLabController::class, 'update']);
        Route::middleware('permission:dental_labs.manage')->delete('/dental-labs/{id}',  [DentalLabController::class, 'destroy']);
        Route::middleware('permission:dental_labs.manage')->post('/dental-labs/{id}/services', [DentalLabController::class, 'storeService']);
        Route::middleware('permission:dental_labs.manage')->post('/dental-labs/{id}/gallery',  [DentalLabController::class, 'storeGallery']);
        Route::middleware('permission:dental_labs.manage')->delete('/dental-lab-services/{id}',[DentalLabController::class, 'destroyService']);
        Route::middleware('permission:dental_labs.view')->get('/dental-lab-orders',            [DentalLabController::class, 'orders']);
        Route::middleware('permission:dental_labs.manage')->post('/dental-lab-orders',         [DentalLabController::class, 'storeOrder']);
        Route::middleware('permission:dental_labs.manage')->post('/dental-lab-orders/{id}/status', [DentalLabController::class, 'updateOrderStatus']);

        // ─── Billing ─────────────────────────────────────────────────────────
        Route::middleware('permission:billing.manage')->group(function () {
            Route::get('/billing/invoices',                    [BillingController::class, 'index']);
            Route::post('/billing/invoices',                   [BillingController::class, 'store']);
            Route::post('/billing/invoices/{id}/send-reminder', [BillingController::class, 'sendInvoiceReminder']);
            Route::post('/billing/invoices/{invoice}/payments',[BillingController::class, 'payment']);
            Route::get('/billing/payments',                    [BillingController::class, 'payments']);
            Route::get('/billing/expenses',                    [BillingController::class, 'expenses']);
            Route::post('/billing/expenses',                   [BillingController::class, 'storeExpense']);
            Route::get('/billing/profit-loss',                 [BillingController::class, 'profitLoss']);
            Route::get('/billing/profit-loss/chart',           [BillingController::class, 'profitLossChart']);
            Route::get('/billing/profit-loss/export',          [BillingController::class, 'exportProfitLoss']);
            Route::post('/billing/profit-loss/send-whatsapp',  [BillingController::class, 'sendProfitLossWhatsApp']);
            Route::get('/billing/expense-categories',          [BillingController::class, 'expenseCategories']);
            Route::post('/billing/expense-categories',         [BillingController::class, 'storeExpenseCategory']);
            Route::post('/billing/expense-categories/{id}',   [BillingController::class, 'updateExpenseCategory']);
            Route::delete('/billing/expense-categories/{id}',  [BillingController::class, 'destroyExpenseCategory']);
        });

        // ─── Support ─────────────────────────────────────────────────────────
       // routes - support section
Route::middleware('permission:support.view')->prefix('support')->group(function () {
    Route::get('/tickets',      [SupportCenterController::class, 'index']);
    Route::get('/tickets/{id}', [SupportCenterController::class, 'show']);
});
Route::middleware('permission:support.manage')->prefix('support')->group(function () {
    Route::post('/tickets',            [SupportCenterController::class, 'store']);
    Route::post('/tickets/{id}/reply', [SupportCenterController::class, 'storeReply']);
});
    });

Route::post('webhook/whatsapp', [WhatsappBotController::class, 'webhook'])
    ->name('clinic.whatsapp-bot.webhook');
