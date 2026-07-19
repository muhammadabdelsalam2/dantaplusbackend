<?php

use App\Http\Controllers\Api\Patient\PatientAppointmentController;
use App\Http\Controllers\Api\Patient\PatientDashboardController;
use App\Http\Controllers\Api\Patient\PatientDocumentController;
use App\Http\Controllers\Api\Patient\PatientInsuranceController;
use App\Http\Controllers\Api\Patient\PatientInvoiceController;
use App\Http\Controllers\Api\Patient\PatientLoginController;
use App\Http\Controllers\Api\Patient\PatientNotificationController;
use App\Http\Controllers\Api\Patient\PatientPaymentController;
use App\Http\Controllers\Api\Patient\PatientProfileController;
use App\Http\Controllers\Api\Patient\PatientRatingController;
use App\Http\Controllers\Api\Patient\PatientTreatmentController;
use Illuminate\Support\Facades\Route;

Route::post('/patient/login', PatientLoginController::class)->middleware('guest');

/*
| ====================================
|  Patient Protected Routes
| ====================================
*/
Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
    Route::prefix('patient')->group(function () {
        Route::get('/dashboard', PatientDashboardController::class);

        Route::get('/profile', [PatientProfileController::class, 'show']);
        Route::post('/profile', [PatientProfileController::class, 'update']);

        Route::get('/appointments', [PatientAppointmentController::class, 'index']);
        Route::post('/appointments', [PatientAppointmentController::class, 'store']);
        Route::get('/appointments/{id}', [PatientAppointmentController::class, 'show']);
        Route::post('/appointments/{id}/cancel', [PatientAppointmentController::class, 'cancel']);
        Route::post('/appointments/{id}/rating', [PatientRatingController::class, 'store']);

        Route::get('/invoices', [PatientInvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [PatientInvoiceController::class, 'show']);
        Route::get('/invoices/{id}/download', [PatientInvoiceController::class, 'download']);

        Route::get('/payments', [PatientPaymentController::class, 'index']);
        Route::post('/payments/{id}/refund-request', [PatientPaymentController::class, 'refundRequest']);

        Route::get('/documents', [PatientDocumentController::class, 'index']);
        Route::get('/documents/{id}', [PatientDocumentController::class, 'show']);
        Route::get('/documents/{id}/download', [PatientDocumentController::class, 'download']);
        Route::get('/radiology', [PatientDocumentController::class, 'radiology']);
        Route::get('/radiology/{id}/download', [PatientDocumentController::class, 'downloadRadiology']);
        Route::get('/notes', [PatientDocumentController::class, 'notes']);
        Route::get('/medical-notes', [PatientDocumentController::class, 'notes']);

        Route::get('/notifications', [PatientNotificationController::class, 'index']);
        Route::get('/notifications/unread', [PatientNotificationController::class, 'unread']);
        Route::post('/notifications/{id}/read', [PatientNotificationController::class, 'markRead']);
        Route::post('/notifications/mark-all-read', [PatientNotificationController::class, 'markAllRead']);

        Route::get('/treatments', [PatientTreatmentController::class, 'index']);

        Route::get('/insurance/claims', [PatientInsuranceController::class, 'claims']);
        Route::get('/insurance/claims/{id}', [PatientInsuranceController::class, 'showClaim']);
        Route::get('/insurance/consents', [PatientInsuranceController::class, 'consents']);
        Route::get('/branches', [PatientAppointmentController::class, 'branches']);
        Route::get('/services', [PatientAppointmentController::class, 'services']);
        Route::get('/doctors', [PatientAppointmentController::class, 'doctors']);
        Route::get('/doctors/{doctorId}/slots', [PatientAppointmentController::class, 'availableSlots']);
    });

});
