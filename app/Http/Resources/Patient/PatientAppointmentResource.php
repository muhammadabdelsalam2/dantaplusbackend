<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'doctor' => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'phone' => $this->doctor->phone,
            ] : null,
            'patient_name' => $this->patient_name,
            'patient_phone' => $this->patient_phone,
            'service_name' => $this->service_name,
            'appointment_at' => optional($this->appointment_at)?->toISOString(),
            'duration_minutes' => $this->duration_minutes ?? $this->duration,
            'branch' => $this->branch,
            'room' => $this->room,
            'payment_type' => $this->payment_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'invoices' => PatientInvoiceResource::collection($this->whenLoaded('invoices')),
        ], static fn ($value) => $value !== null);
    }
}
