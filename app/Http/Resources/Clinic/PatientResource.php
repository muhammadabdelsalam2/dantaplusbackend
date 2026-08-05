<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approvedId = $this->approvedId();

        return array_filter([
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_number' => $this->patient_number,
            'name' => $this->user?->name,
            'full_name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->phone ?: $this->user?->phone,
            'approved_id' => $approvedId,
            'date_of_birth' => optional($this->date_of_birth)?->toDateString(),
            'age' => $this->age,
            'gender' => $this->gender,
            'address' => $this->address,
            'medical_history' => $this->medical_history,
            'allergies' => $this->allergies,
            'current_medication' => $this->current_medication,
            'insurance' => array_filter([
                'provider' => $this->insurance_provider,
                'number' => $this->insurance_number,
                'policy_number' => $this->insurance_number,
                'approved_id' => $approvedId,
            ], static fn ($value) => $value !== null),
            'insurance_company' => $this->insuranceCompany ? [
                'id' => $this->insuranceCompany->id,
                'name' => $this->insuranceCompany->name,
            ] : null,
            'payment_type' => $this->payment_type,
            'notes' => $this->notes,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private function approvedId(): ?string
    {
        $approval = \App\Models\InsuranceApproval::query()
            ->where('clinic_id', $this->clinic_id)
            ->where('patient_id', $this->id)
            ->where('status', 'Approved')
            ->where(function ($query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString());
            })
            ->latest('date')
            ->latest('id')
            ->first(['id', 'ref_id', 'approval_number', 'code']);

        return $approval
            ? (string) ($approval->ref_id ?: $approval->approval_number ?: $approval->code ?: $approval->id)
            : null;
    }
}
