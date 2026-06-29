<?php

use App\Http\Controllers\Api\Patient\PatientAppointmentController;
use App\Http\Controllers\Api\Patient\PatientDashboardController;
use App\Http\Controllers\Api\Patient\PatientDocumentController;
use App\Http\Controllers\Api\Patient\PatientInsuranceController;
use App\Http\Controllers\Api\Patient\PatientInvoiceController;
use App\Http\Controllers\Api\Patient\PatientPaymentController;
use App\Http\Controllers\Api\Patient\PatientProfileController;
use App\Http\Controllers\Api\Patient\PatientRatingController;
use Illuminate\Support\Facades\Route;

/*
| ====================================
|  Patient Protected Routes
| ====================================
*/
Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
    Route::prefix('patient')->group(function () {
        Route::get('/dashboard', PatientDashboardController::class);

        Route::get('/profile', [PatientProfileController::class, 'show']);
        Route::patch('/profile', [PatientProfileController::class, 'update']);

        Route::get('/appointments', [PatientAppointmentController::class, 'index']);
        Route::post('/appointments', [PatientAppointmentController::class, 'store']);
        Route::get('/appointments/{id}', [PatientAppointmentController::class, 'show']);
        Route::patch('/appointments/{id}/cancel', [PatientAppointmentController::class, 'cancel']);
        Route::post('/appointments/{id}/rating', [PatientRatingController::class, 'store']);

        Route::get('/invoices', [PatientInvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [PatientInvoiceController::class, 'show']);

        Route::get('/payments', [PatientPaymentController::class, 'index']);
        Route::post('/payments/{id}/refund-request', [PatientPaymentController::class, 'refundRequest']);

        Route::get('/documents', [PatientDocumentController::class, 'index']);
        Route::get('/documents/{id}', [PatientDocumentController::class, 'show']);
        Route::get('/radiology', [PatientDocumentController::class, 'radiology']);
        Route::get('/notes', [PatientDocumentController::class, 'notes']);
        Route::get('/medical-notes', [PatientDocumentController::class, 'notes']);

        Route::get('/insurance/claims', [PatientInsuranceController::class, 'claims']);
        Route::get('/insurance/claims/{id}', [PatientInsuranceController::class, 'showClaim']);
        Route::get('/insurance/consents', [PatientInsuranceController::class, 'consents']);
        Route::get('/doctors', [PatientAppointmentController::class, 'doctors']);
Route::get('/doctors/{doctorId}/slots', [PatientAppointmentController::class, 'availableSlots']);
    });


});
