<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_number' => $this->patient_number,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->phone ?: $this->user?->phone,
            'date_of_birth' => optional($this->date_of_birth)?->toDateString(),
            'gender' => $this->gender,
            'address' => $this->address,
            'medical_history' => $this->medical_history,
            'allergies' => $this->allergies,
            'current_medication' => $this->current_medication,
            'insurance' => array_filter([
                'provider' => $this->insurance_provider,
                'number' => $this->insurance_number,
            ], static fn ($value) => $value !== null),
            'insurance_company' => $this->insuranceCompany ? [
                'id' => $this->insuranceCompany->id,
                'name' => $this->insuranceCompany->name,
            ] : null,
            'notes' => $this->notes,
        ], static fn ($value) => $value !== null && $value !== []);
    }
}
