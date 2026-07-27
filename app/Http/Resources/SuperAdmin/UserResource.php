<?php

namespace App\Http\Resources\SuperAdmin;

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

        $role = $this->relationLoaded('roles')
            ? $this->roles->first()?->name
            : $this->getRoleNames()->first();

        $entity = $this->clinic?->name ?? $this->company?->name ?? $this->lab?->name;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'user' => [
                'name' => $this->name,
                'email' => $this->email,
                'avatar_url' => $this->avatar_url,
            ],
            'role' => $role,
            'entity' => $entity,
            'status' => $this->is_active ? 'Active' : 'Inactive',
            'last_login' => optional($this->last_login_at ?? $this->updated_at)->format('d/m/Y'),
        ];
    }
}
