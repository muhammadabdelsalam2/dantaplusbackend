<?php

namespace App\Http\Resources\SuperAdmin;

use App\Support\UserRoleManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->relationLoaded('roles')
            ? $this->roles->first()
            : $this->roles()->first();
        $roleName = $role?->name;

        $entity = $this->entityForRole($roleName);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
            'is_active' => (bool) $this->is_active,
            'user' => [
                'name' => $this->name,
                'email' => $this->email,
                'avatar_url' => $this->avatar_url,
            ],
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => Str::of($role->name)->replace(['-', '_'], ' ')->title()->toString(),
            ] : null,
            'entity' => $entity,
            'assigned_to' => $entity ? [
                'type' => $entity['type'],
                'id' => $entity['id'],
                'name' => $entity['name'],
            ] : null,
            'status' => $this->is_active ? 'Active' : 'Inactive',
            'last_login' => optional($this->last_login_at ?? $this->updated_at)->format('d/m/Y'),
        ];
    }

    private function entityForRole(?string $role): ?array
    {
        return match (UserRoleManager::entityTypeForRole($role)) {
            'clinic' => $this->clinic ? [
                'type' => 'clinic',
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'email' => $this->clinic->email,
                'phone' => $this->clinic->phone,
                'address' => $this->clinic->address,
            ] : null,
            'lab' => $this->lab ? [
                'type' => 'lab',
                'id' => $this->lab->id,
                'name' => $this->lab->name,
                'email' => $this->lab->email,
                'phone' => $this->lab->phone,
                'city' => $this->lab->city,
            ] : null,
            'material_company' => $this->company ? [
                'type' => 'material_company',
                'id' => $this->company->id,
                'name' => $this->company->name,
                'email' => $this->company->email,
                'phone' => $this->company->phone,
                'city' => $this->company->city,
                'country' => $this->company->country,
            ] : null,
            default => null,
        };
    }
}
