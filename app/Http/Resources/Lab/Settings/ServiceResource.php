<?php

namespace App\Http\Resources\Lab\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_name' => $this->service_name,
            'price' => $this->price,
            'turnaround_time_days' => $this->turnaround_time_days,
        ];
    }
}
