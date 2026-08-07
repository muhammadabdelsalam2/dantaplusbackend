<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $source = $this->added_by ? 'Admin' : 'System Signup';

        return [
            'id' => $this->id,
            'clinic_name' => $this->name,
            'owner_name' => $this->owner_name,
            'owner_email' => $this->email,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'subscription_plan' => $this->subscription_plan,
            'payment_method' => $this->payment_method,
            'branches_count' => (int) ($this->branches_count ?? 0),
            'users_count' => (int) ($this->users_count ?? 0),
            'max_users' => (int) $this->max_users,
            'max_branches' => (int) $this->max_branches,
            'status' => $this->status,
            'source' => $source,
            'created_at' => optional($this->created_at)->toISOString(),
            'date_added' => optional($this->created_at)->format('d/m/Y'),

            'branches' => (int) ($this->branches_count ?? 0),
            'subscription' => $this->subscription_plan,
            'clinic' => [
                'name' => $this->name,
                'owner' => $this->owner_name,
                'email' => $this->email,
            ],
        ];
    }
}
