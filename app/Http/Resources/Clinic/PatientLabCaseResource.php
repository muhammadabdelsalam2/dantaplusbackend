<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientLabCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'case_number' => $this->case_number,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'lab' => $this->lab ? [
                'id' => $this->lab->id,
                'name' => $this->lab->name,
            ] : null,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => optional($this->due_date)?->toDateString(),
            'case_type' => $this->case_type,
            'tooth_numbers' => $this->tooth_numbers,
            'description' => $this->description,
            'created_at' => optional($this->created_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
