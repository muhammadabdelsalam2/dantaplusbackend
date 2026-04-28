<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicDentalLabServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'dental_lab_id' => $this->lab_id,
            'provider_id' => $this->lab_id,
            'service_name' => $this->service_name,
            'name' => $this->service_name,
            'price' => (float) $this->price,
            'turnaround_time_days' => $this->turnaround_time_days,
            'duration_days' => $this->turnaround_time_days,
        ];
    }
}
