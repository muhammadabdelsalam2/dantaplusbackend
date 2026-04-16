<?php

namespace App\Http\Resources\Company;

use App\Support\UserRoleManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => UserRoleManager::primaryRole($this->resource),
            'status' => $this->status?->value ?? $this->status,
            'company_id' => $this->company_id,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
