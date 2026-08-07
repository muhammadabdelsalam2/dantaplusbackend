<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_details' => [
                'clinic_name' => $this->name,
                'owner_name' => $this->owner_name,
                'owner_email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'subdomain' => $this->subdomain,
                'subscription_plan' => $this->subscription_plan,
                'payment_method' => $this->payment_method,
                'status' => $this->status,
                'start_date' => optional($this->start_date)->toISOString(),
                'expiry_date' => optional($this->expiry_date)->toISOString(),
                'enabled_modules' => $this->whenLoaded('modules', fn () => $this->modules
                    ->pluck('module')
                    ->values()
                    ->all(), []),
                'max_users' => (int) $this->max_users,
                'max_branches' => (int) $this->max_branches,
            ],
            'users' => $this->relationLoaded('users')
                ? ClinicUserResource::collection($this->users)->resolve()
                : [],
            'payments' => $this->relationLoaded('payments')
                ? ClinicPaymentHistoryResource::collection($this->payments)->resolve()
                : [],
            'usage' => [
                'total_patients' => (int) ($this->patients_count ?? 0),
                'appointments_this_month' => (int) ($this->appointments_this_month_count ?? 0),
                'messages_sent' => (int) ($this->messages_sent_count ?? 0),
                'storage_used' => 0,
                'storage_limit' => null,
            ],
        ];
    }
}
