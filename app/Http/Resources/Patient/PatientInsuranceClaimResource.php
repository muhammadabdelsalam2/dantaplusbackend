<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientInsuranceClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'claim_number' => $this->claim_number,
            'status' => $this->status,
            'gross_amount' => (float) $this->gross_amount,
            'approved_amount' => (float) $this->approved_amount,
            'paid_amount' => (float) $this->paid_amount,
            'patient_responsibility' => (float) $this->patient_share_amount,
            'insurance_share_amount' => (float) $this->insurance_share_amount,
            'patient_balance_after_insurance' => max((float) $this->gross_amount - (float) $this->approved_amount, 0),
            'insurance_company' => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'code' => $this->company->code,
            ] : null,
            'appointment' => $this->appointment ? [
                'id' => $this->appointment->id,
                'appointment_at' => optional($this->appointment->appointment_at)?->toISOString(),
                'service_name' => $this->appointment->service_name,
            ] : null,
            'invoice' => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'total' => (float) $this->invoice->total,
                'status' => $this->invoice->status,
            ] : null,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'service_name' => $item->service_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_amount' => (float) $item->total_amount,
            ])->values()),
            'consent' => [
                'required' => (bool) $this->patient_consent_required,
                'uploaded_at' => optional($this->patient_consent_uploaded_at)?->toISOString(),
                'document_id' => $this->patient_consent_document_id,
            ],
            'submitted_at' => optional($this->submitted_at)?->toISOString(),
            'reviewed_at' => optional($this->reviewed_at)?->toISOString(),
            'settled_at' => optional($this->settled_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
