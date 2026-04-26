<?php

namespace App\Observers;

use App\Models\ClinicAppointment;
use App\Services\Clinic\Settings\ClinicAppointmentAutomationService;

class ClinicAppointmentObserver
{
    public function created(ClinicAppointment $appointment): void
    {
        app(ClinicAppointmentAutomationService::class)->handleStatusChange(
            $appointment->fresh(['clinic', 'patient.user', 'doctor']),
            null,
            $appointment->status,
        );
    }

    public function updated(ClinicAppointment $appointment): void
    {
        if (! $appointment->wasChanged('status')) {
            return;
        }

        app(ClinicAppointmentAutomationService::class)->handleStatusChange(
            $appointment->fresh(['clinic', 'patient.user', 'doctor']),
            $appointment->getOriginal('status'),
            $appointment->status,
        );
    }
}
