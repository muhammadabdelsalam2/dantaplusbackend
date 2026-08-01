<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentalChartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'clinic_id' => $this->clinic_id,
            'tooth_number' => $this->tooth_number,
            'status' => $this->status,
            'is_present' => (bool) ($this->is_present ?? true),
            'target_area' => $this->target_area,
            'procedure_id' => $this->procedure_id,
            'procedure' => $this->procedure ? [
                'id' => $this->procedure->id,
                'name' => $this->procedure->name,
            ] : null,
            'treating_doctor_id' => $this->treating_doctor_id,
            'treating_doctor' => $this->treatingDoctor ? [
                'id' => $this->treatingDoctor->id,
                'name' => $this->treatingDoctor->name,
            ] : null,
            'billing_method' => $this->billing_method,
            'clinical_notes' => $this->clinical_notes,
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
