<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
   public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'clinic_id' => $this->clinic_id,
        'patient_id' => $this->patient_id,
        'patient_name' => $this->patient_name,
        'patient_phone' => $this->patient_phone,
        'doctor' => $this->doctor ? [
            'id' => $this->doctor->id,
            'name' => $this->doctor->name,
        ] : null,
        'service_name' => $this->service_name,
        'appointment_at' => optional($this->appointment_at)?->toISOString(),
        'duration' => $this->duration ?? $this->duration_minutes,
        'branch' => $this->branch,
        'room' => $this->room,
        'payment_type' => $this->payment_type,
        'status' => $this->status,
        'notes' => $this->notes,
    ];
}
}
