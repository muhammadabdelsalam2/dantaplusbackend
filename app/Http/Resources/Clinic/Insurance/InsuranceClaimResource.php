<?php

namespace App\Http\Resources\Clinic\Insurance;

use App\Http\Resources\PatientDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'claim_number' => $this->claim_number,
            'clinic_id' => $this->clinic_id,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'service_date' => optional($this->service_date)?->toDateString(),
            'coverage_percentage' => (float) $this->coverage_percentage,
            'gross_amount' => (float) $this->gross_amount,
            'patient_share_amount' => (float) $this->patient_share_amount,
            'insurance_share_amount' => (float) $this->insurance_share_amount,
            'approved_amount' => (float) $this->approved_amount,
            'paid_amount' => (float) $this->paid_amount,
            'notes' => $this->notes,
            'status_notes' => $this->status_notes,
            'company' => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'code' => $this->company->code,
            ] : null,
            'patient' => $this->patient ? [
                'id' => $this->patient->id,
                'patient_number' => $this->patient->patient_number,
                'name' => $this->patient->user?->name,
            ] : null,
            'appointment' => $this->appointment ? [
                'id' => $this->appointment->id,
                'appointment_at' => optional($this->appointment->appointment_at)?->toISOString(),
            ] : null,
            'invoice' => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'total' => (float) $this->invoice->total,
                'status' => $this->invoice->status,
            ] : null,
            'submitted_at' => optional($this->submitted_at)?->toISOString(),
            'reviewed_at' => optional($this->reviewed_at)?->toISOString(),
            'settled_at' => optional($this->settled_at)?->toISOString(),
            'patient_consent_required' => (bool) $this->patient_consent_required,
            'patient_consent_uploaded_at' => optional($this->patient_consent_uploaded_at)?->toISOString(),
            'consent_upload_required' => $this->patient_consent_required && !$this->patient_consent_document_id,
            'items' => InsuranceClaimItemResource::collection($this->whenLoaded('items')),
            'patient_consent' => $this->whenLoaded('patientConsent', function () {
                return new PatientDocumentResource($this->patientConsent);
            }),
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'updated_by' => $this->updater ? [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
            ] : null,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
