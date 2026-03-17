<?php

namespace App\Http\Resources\Lab\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lab_id' => $this->id,
            'lab_name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'working_hours' => $this->working_hours,
            'logo_url' => $this->logo_url,
        ];
    }
}
