<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicDentalLabOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status === 'Delivered'
            ? 'delivered'
            : ($this->due_date && now()->toDateString() > $this->due_date->toDateString() ? 'overdue' : ($this->status === 'Accepted' ? 'accepted' : 'pending'));

        $lab = $this->lab ? [
            'id' => $this->lab->id,
            'name' => $this->lab->name,
        ] : null;

        $service = [
            'id' => null,
            'name' => $this->case_type,
        ];

        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'case_number' => $this->case_number,
            'dental_lab' => $lab,
            'provider' => $lab,
            'patient' => $this->patient ? [
                'id' => $this->patient->id,
                'name' => $this->patient->user?->name,
            ] : null,
            'lab_service' => $service,
            'service' => $service,
            'status' => $status,
            'due_date' => optional($this->due_date)?->toDateString(),
            'delivered_at' => optional($this->delivered_at)?->toISOString(),
            'notes' => $this->description,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
