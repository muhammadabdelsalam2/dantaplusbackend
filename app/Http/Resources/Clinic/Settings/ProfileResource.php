<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->getRoleNames()->first() ?? $this->role,
            'avatar' => $this->avatar_url,
        ];
    }
}
