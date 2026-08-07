<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ClinicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->relationLoaded('roles')
            ? $this->roles->first()
            : $this->roles()->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
            'avatar_url' => $this->avatar_url,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => Str::of($role->name)->replace(['-', '_'], ' ')->title()->toString(),
            ] : null,
            'last_login' => optional($this->last_login_at)->toISOString(),
        ];
    }
}
