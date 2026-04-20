<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'clinic_id' => $this->clinic_id,
            'patient' => $this->patient ? [
                'id' => $this->patient->id,
                'name' => $this->patient->user?->name,
            ] : null,
            'appointment_id' => $this->appointment_id,
            'total' => (float) $this->total,
            'paid' => (float) $this->paid,
            'remaining' => (float) $this->remaining,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'issued_at' => optional($this->issued_at)?->toDateString(),
            'notes' => $this->notes,
            'payments' => ClinicPaymentResource::collection($this->whenLoaded('payments')),
        ], static fn ($value) => $value !== null);
    }
}
