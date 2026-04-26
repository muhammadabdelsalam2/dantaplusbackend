<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'working_hours_from' => $this->working_hours_from,
            'working_hours_to' => $this->working_hours_to,
            'working_hours' => trim(implode(' - ', array_filter([$this->working_hours_from, $this->working_hours_to]))),
            'notes' => $this->notes,
            'rooms_count' => $this->rooms_count,
            'status' => $this->status,
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
                'phone' => $this->manager->phone,
            ] : null,
        ];
    }
}
