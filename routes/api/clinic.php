<?php

use App\Http\Controllers\Api\Clinic\AppointmentController;
use App\Http\Controllers\Api\Clinic\BillingController;
use App\Http\Controllers\Api\Clinic\ClinicController;
use App\Http\Controllers\Api\Clinic\Insurance\InsuranceClaimController;
use App\Http\Controllers\Api\Clinic\Insurance\InsuranceCompanyController;
use App\Http\Controllers\Api\Clinic\PatientController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicDoctorReminderSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicFeedbackSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicAppointmentSettingsController;
use App\Http\Controllers\Api\Clinic\Settings\ClinicAppearanceSettingsController;
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
use App\Http\Controllers\Api\Clinic\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('clinic')
    ->middleware(['auth:sanctum', 'role:clinic_admin|doctor|nurse|accountant|receptionist|staff'])
    ->group(function () {
        Route::prefix('settings')->group(function () {
            Route::get('/profile', [SettingsProfileController::class, 'show']);
            Route::post('/profile', [SettingsProfileController::class, 'update']);
            Route::patch('/profile/password', [SettingsProfileController::class, 'updatePassword']);

            Route::middleware('permission:settings.manage')->group(function () {
                Route::get('/general', [GeneralSettingsController::class, 'show']);
                Route::post('/general', [GeneralSettingsController::class, 'update']);

                Route::get('/financial', [ClinicFinancialSettingsController::class, 'show']);
                Route::post('/financial', [ClinicFinancialSettingsController::class, 'update']);

                Route::get('/appointments', [ClinicAppointmentSettingsController::class, 'show']);
                Route::post('/appointments', [ClinicAppointmentSettingsController::class, 'update']);
                Route::post('/appointments', [ClinicAppointmentSettingsController::class, 'update']);

                Route::get('/reminders', [ClinicReminderSettingsController::class, 'show']);
                Route::patch('/reminders', [ClinicReminderSettingsController::class, 'update']);
                Route::patch('/reminders', [ClinicReminderSettingsController::class, 'update']);

                Route::get('/doctor-reminders', [ClinicDoctorReminderSettingsController::class, 'show']);
                Route::patch('/doctor-reminders', [ClinicDoctorReminderSettingsController::class, 'update']);
                Route::post('/doctor-reminders/trigger', [ClinicDoctorReminderSettingsController::class, 'trigger']);
                Route::get('/doctor-reminders/logs', [ClinicDoctorReminderSettingsController::class, 'logs']);

                Route::get('/queue-notifications', [ClinicQueueNotificationSettingsController::class, 'show']);
                Route::patch('/queue-notifications', [ClinicQueueNotificationSettingsController::class, 'update']);
                Route::post('/queue-notifications/test', [ClinicQueueNotificationSettingsController::class, 'test']);

                Route::get('/feedback', [ClinicFeedbackSettingsController::class, 'show']);
                Route::patch('/feedback', [ClinicFeedbackSettingsController::class, 'update']);
                Route::get('/feedback/logs', [ClinicFeedbackSettingsController::class, 'logs']);

                Route::get('/security', [ClinicSecuritySettingsController::class, 'show']);
                Route::patch('/security', [ClinicSecuritySettingsController::class, 'update']);
                Route::post('/security/backup', [ClinicSecuritySettingsController::class, 'backup']);

                Route::get('/appearance', [ClinicAppearanceSettingsController::class, 'show']);
                Route::patch('/appearance', [ClinicAppearanceSettingsController::class, 'update']);

                Route::get('/integrations', [ClinicIntegrationSettingsController::class, 'show']);
                Route::post('/integrations/connect/google', [ClinicIntegrationSettingsController::class, 'connectGoogle']);
                Route::post('/integrations/connect/outlook', [ClinicIntegrationSettingsController::class, 'connectOutlook']);

                Route::get('/service-pricing', [ClinicServicePricingController::class, 'index']);
                Route::post('/service-pricing', [ClinicServicePricingController::class, 'store']);
                Route::patch('/service-pricing/{serviceId}', [ClinicServicePricingController::class, 'update']);
                Route::delete('/service-pricing/{id}', [ClinicServicePricingController::class, 'destroy']);

                Route::get('/clinic-info', [ClinicInfoController::class, 'show']);
                Route::post('/clinic-info', [ClinicInfoController::class, 'update']);

                Route::get('/branches', [SettingsBranchController::class, 'index']);
                Route::post('/branches', [SettingsBranchController::class, 'store']);
                Route::get('/branches/{id}', [SettingsBranchController::class, 'show']);
                Route::post('/branches/{id}', [SettingsBranchController::class, 'update']);
                Route::delete('/branches/{id}', [SettingsBranchController::class, 'destroy']);

                Route::get('/dentists', [SettingsDentistController::class, 'index']);
                Route::post('/dentists', [SettingsDentistController::class, 'store']);
                Route::get('/dentists/{id}', [SettingsDentistController::class, 'show']);
                Route::patch('/dentists/{id}', [SettingsDentistController::class, 'update']);
                Route::delete('/dentists/{id}', [SettingsDentistController::class, 'destroy']);
            });

        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/insurance-price-lists', [ClinicInsurancePriceListController::class, 'index']);
            Route::post('/insurance-price-lists', [ClinicInsurancePriceListController::class, 'store']);
            Route::post('/insurance-price-lists/import', [ClinicInsurancePriceListController::class, 'import']);
            Route::patch('/insurance-price-lists/{id}', [ClinicInsurancePriceListController::class, 'update']);
            Route::delete('/insurance-price-lists/{id}', [ClinicInsurancePriceListController::class, 'destroy']);
            Route::post('/reminders/trigger', [ClinicReminderSettingsController::class, 'trigger']);
            Route::get('/reminders/logs', [ClinicReminderSettingsController::class, 'logs']);
        });

        Route::prefix('insurance')->group(function () {
            Route::middleware('permission:insurance.view')->group(function () {
                Route::get('/companies', [InsuranceCompanyController::class, 'index']);
                Route::get('/companies/{id}', [InsuranceCompanyController::class, 'show']);
                Route::get('/claims', [InsuranceClaimController::class, 'index']);
                Route::get('/claims/{id}', [InsuranceClaimController::class, 'show']);
            });

            Route::middleware('permission:insurance.create')->group(function () {
                Route::post('/companies', [InsuranceCompanyController::class, 'store']);
                Route::post('/claims', [InsuranceClaimController::class, 'store']);
            });

            Route::middleware('permission:insurance.update')->group(function () {
                Route::patch('/companies/{id}', [InsuranceCompanyController::class, 'update']);
                Route::patch('/claims/{id}', [InsuranceClaimController::class, 'update']);
            });

            Route::middleware('permission:insurance.delete')->group(function () {
                Route::delete('/companies/{id}', [InsuranceCompanyController::class, 'destroy']);
                Route::delete('/claims/{id}', [InsuranceClaimController::class, 'destroy']);
            });
        });

        Route::middleware('role:clinic_admin')->group(function () {
            Route::get('/my-clinic', [ClinicController::class, 'getMyClinic']);
            Route::post('/update-my-clinic', [ClinicController::class, 'updateMyClinic']);
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::patch('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
        });

        Route::middleware('permission:patients.view')->get('/patients', [PatientController::class, 'index']);
        Route::middleware('permission:patients.create')->post('/patients', [PatientController::class, 'store']);
        Route::middleware('permission:patients.view')->get('/patients/{id}', [PatientController::class, 'show']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/dental-chart', [PatientController::class, 'dentalChart']);
        Route::middleware('permission:patients.update')->post('/patients/{id}/dental-chart', [PatientController::class, 'storeDentalChart']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/radiology', [PatientController::class, 'radiology']);
        Route::middleware('permission:patients.update')->post('/patients/{id}/radiology/upload', [PatientController::class, 'uploadRadiology']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/labs', [PatientController::class, 'labCases']);
        Route::middleware('permission:labs.send')->post('/patients/{id}/labs', [PatientController::class, 'sendLabCase']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/discussion', [PatientController::class, 'discussion']);
        Route::middleware('permission:communication.send')->post('/patients/{id}/discussion', [PatientController::class, 'storeDiscussion']);
        Route::middleware('permission:patients.view')->get('/patients/{id}/analytics', [PatientController::class, 'analytics']);

        Route::middleware('permission:appointments.view')->get('/appointments', [AppointmentController::class, 'index']);
        Route::middleware('permission:appointments.create')->post('/appointments', [AppointmentController::class, 'store']);
        Route::middleware('permission:appointments.update')->patch('/appointments/{id}', [AppointmentController::class, 'update']);
        Route::middleware('permission:appointments.view')->get('/appointments/{id}', [AppointmentController::class, 'show']);

        Route::middleware('permission:treatments.manage')->get('/treatments', [TreatmentController::class, 'index']);
        Route::middleware('permission:treatments.manage')->post('/treatments', [TreatmentController::class, 'store']);
        Route::middleware('permission:treatments.manage')->get('/treatments/{id}', [TreatmentController::class, 'show']);

        Route::middleware('permission:billing.manage')->get('/billing/invoices', [BillingController::class, 'index']);
        Route::middleware('permission:billing.manage')->post('/billing/invoices', [BillingController::class, 'store']);
        Route::middleware('permission:billing.manage')->get('/billing/invoices/{id}', [BillingController::class, 'show']);
        Route::middleware('permission:billing.manage')->post('/billing/invoices/{invoice}/payments', [BillingController::class, 'payment']);
    });
