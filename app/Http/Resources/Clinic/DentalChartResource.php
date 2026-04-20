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
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
