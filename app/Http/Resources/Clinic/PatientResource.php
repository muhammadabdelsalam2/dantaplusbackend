<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approval = $this->activeInsuranceApproval();
        $isInsurance = $this->payment_type === 'insurance';

        return array_filter([
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_number' => $this->patient_number,
            'name' => $this->user?->name,
            'full_name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->phone ?: $this->user?->phone,
            'approved_id' => $isInsurance ? $approval?->id : null,
            'insurance_approval' => $isInsurance && $approval ? $this->approvalSummary($approval) : null,
            'insurance_approval_required' => $isInsurance && ! $approval,
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
                'approved_id' => $isInsurance ? $approval?->id : null,
            ], static fn ($value) => $value !== null),
            'insurance_company' => $this->insuranceCompany ? [
                'id' => $this->insuranceCompany->id,
                'name' => $this->insuranceCompany->name,
            ] : null,
            'payment_type' => $this->payment_type,
            'notes' => $this->notes,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private function activeInsuranceApproval(): ?\App\Models\InsuranceApproval
    {
        if ($this->payment_type !== 'insurance') {
            return null;
        }

        if ($this->relationLoaded('latestActiveInsuranceApproval')) {
            return $this->latestActiveInsuranceApproval;
        }

        return \App\Models\InsuranceApproval::query()
            ->where('clinic_id', $this->clinic_id)
            ->where('patient_id', $this->id)
            ->where('status', 'Approved')
            ->where(function ($query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString());
            })
            ->latest('date')
            ->latest('id')
            ->first();
    }

    private function approvalSummary(\App\Models\InsuranceApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'status' => $approval->status,
            'approved_amount' => (float) $approval->approved_amount,
            'coverage_percent' => (float) $approval->coverage_percent,
            'expiry_date' => optional($approval->expiry_date)?->toDateString(),
        ];
    }
}
