<?php

use App\Http\Controllers\Api\Clinic\AppointmentController;
use App\Http\Controllers\Api\Clinic\BillingController;
use App\Http\Controllers\Api\Clinic\PatientController;
use App\Http\Controllers\Api\Clinic\TreatmentController;
use App\Http\Controllers\Api\Clinic\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('clinic')
    ->middleware(['auth:sanctum', 'role:clinic_admin|doctor|nurse|accountant|receptionist|staff'])
    ->group(function () {
        Route::middleware('role:clinic_admin')->group(function () {
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
        Route::middleware('permission:appointments.view')->get('/appointments/{id}', [AppointmentController::class, 'show']);

        Route::middleware('permission:treatments.manage')->get('/treatments', [TreatmentController::class, 'index']);
        Route::middleware('permission:treatments.manage')->post('/treatments', [TreatmentController::class, 'store']);
        Route::middleware('permission:treatments.manage')->get('/treatments/{id}', [TreatmentController::class, 'show']);

        Route::middleware('permission:billing.manage')->get('/billing/invoices', [BillingController::class, 'index']);
        Route::middleware('permission:billing.manage')->post('/billing/invoices', [BillingController::class, 'store']);
        Route::middleware('permission:billing.manage')->get('/billing/invoices/{id}', [BillingController::class, 'show']);
        Route::middleware('permission:billing.manage')->post('/billing/invoices/{invoice}/payments', [BillingController::class, 'payment']);
    });
