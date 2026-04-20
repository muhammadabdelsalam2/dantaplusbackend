<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->getRoleNames()->first(),
            'clinic_id' => $this->clinic_id,
        ], static fn ($value) => $value !== null);
    }
}
