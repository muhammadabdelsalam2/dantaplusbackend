<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'invoice_id' => $this->clinic_invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'patient_name' => $this->invoice?->patient?->user?->name,
            'doctor_name' => $this->invoice?->doctor?->name,
            'invoice_total' => $this->invoice ? (float) $this->invoice->total : null,
            'invoice_paid' => $this->invoice ? (float) $this->invoice->paid : null,
            'invoice_remaining' => $this->invoice ? (float) $this->invoice->remaining : null,
            'invoice_status' => $this->invoice?->status,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'paid_at' => optional($this->paid_at)?->toISOString(),
            'notes' => $this->notes,
            'recorded_by' => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null,
        ], static fn ($value) => $value !== null);
    }
}
