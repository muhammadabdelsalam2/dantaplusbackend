<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RadiologyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'clinic_id' => $this->clinic_id,
            'modality' => $this->modality,
            'notes' => $this->notes,
            'file_path' => $this->file_path,
            'status' => $this->status,
            'created_at' => optional($this->created_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
