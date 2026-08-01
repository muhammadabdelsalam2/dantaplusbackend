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
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status ?: ((int) ($this->is_active ?? 1) === 1 ? 'Active' : 'Inactive'),
            'is_active' => (bool) ($this->is_active ?? true),
        ], static fn ($value) => $value !== null);
    }
}
