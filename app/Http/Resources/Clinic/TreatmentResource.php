<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient' => $this->patient ? [
                'id' => $this->patient->id,
                'name' => $this->patient->user?->name,
            ] : null,
            'doctor' => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ] : null,
            'title' => $this->title,
            'description' => $this->description,
            'tooth_number' => $this->tooth_number,
            'sessions_count' => $this->sessions_count,
            'treatment_date' => optional($this->treatment_date)?->toDateString(),
            'cost' => (float) $this->cost,
            'status' => $this->status,
        ], static fn ($value) => $value !== null);
    }
}
