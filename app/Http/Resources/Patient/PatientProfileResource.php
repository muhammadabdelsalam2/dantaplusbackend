<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'patient_id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->phone ?: $this->user?->phone,
            'patient_number' => $this->patient_number,
            'clinic_id' => $this->clinic_id,
            'date_of_birth' => optional($this->date_of_birth)?->toDateString(),
            'gender' => $this->gender,
            'address' => $this->address,
            'medical_history' => $this->medical_history,
            'allergies' => $this->allergies,
            'current_medication' => $this->current_medication,
            'insurance_provider' => $this->insurance_provider,
            'insurance_number' => $this->insurance_number,
            'notes' => $this->notes,
            'clinic' => $this->clinic ? [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'phone' => $this->clinic->phone,
                'email' => $this->clinic->email,
                'address' => $this->clinic->address,
            ] : null,
        ], static fn ($value) => $value !== null);
    }
}
