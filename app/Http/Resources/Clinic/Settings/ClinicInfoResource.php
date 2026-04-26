<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicInfoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner_name' => $this->owner_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'clinic_type' => $this->clinic_type?->value ?? $this->clinic_type,
            'subdomain' => $this->subdomain,
            'notes' => $this->notes,
            'subscription_plan' => $this->subscription_plan,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'start_date' => optional($this->start_date)->toDateString(),
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'max_users' => $this->max_users,
            'max_branches' => $this->max_branches,
        ];
    }
}
