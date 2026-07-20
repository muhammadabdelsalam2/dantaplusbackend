<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'total' => (float) $this->total,
            'paid' => (float) $this->paid,
            'remaining' => (float) $this->remaining,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'issued_at' => optional($this->issued_at)?->toDateString(),
            'due_date' => optional($this->due_date)?->toDateString(),
            'file_url' => url('/api/patient/invoices/' . $this->id . '/download'),
            'appointment' => $this->appointment ? [
                'id' => $this->appointment->id,
                'service_name' => $this->appointment->service_name,
                'appointment_at' => optional($this->appointment->appointment_at)?->toISOString(),
                'status' => $this->appointment->status,
            ] : null,
            'doctor' => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ] : null,
            'payments' => PatientPaymentResource::collection($this->whenLoaded('payments')),
        ], static fn ($value) => $value !== null);
    }
}
