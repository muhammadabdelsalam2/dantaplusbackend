<?php

namespace App\Http\Resources\SuperAdmin;

use App\Support\UserRoleManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        
        $entityType = null;
        $entityId = null;

        if ($this->relationLoaded('doctor') && $this->doctor) {
            $entityType = 'doctor';
            $entityId = $this->doctor->id ?? null;
        } elseif ($this->relationLoaded('patient') && $this->patient) {
            $entityType = 'patient';
            $entityId = $this->patient->id ?? null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'role' => UserRoleManager::primaryRole($this->resource),
            'roles' => $this->getRoleNames()->values()->all(),
            'lab_id' => $this->lab_id,
            'entity' => [
                'type' => $entityType,
                'id' => $entityId,
            ],
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
