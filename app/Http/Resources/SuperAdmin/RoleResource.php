<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,

            // only included when service attaches them
            'permissions' => $this->when(isset($this->permissions_list), fn () => $this->permissions_list),
            'users_count' => $this->when(isset($this->users_count), fn () => $this->users_count),

            'created_at' => $this->when(isset($this->created_at), fn () => $this->created_at),
            'updated_at' => $this->when(isset($this->updated_at), fn () => $this->updated_at),
        ];
    }
}
