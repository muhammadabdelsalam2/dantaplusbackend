<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $commissionRates = is_array($this->commission_rates) ? $this->commission_rates : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->getRoleNames()->first() ?? $this->role,
            'specialization' => $this->doctor?->specialization,
            'license_number' => $this->doctor?->license_number,
            'working_hours_from' => $this->doctor?->working_hours_from,
            'working_hours_to' => $this->doctor?->working_hours_to,
            'insurance_commission' => $commissionRates['insurance_commission'] ?? 15,
            'cash_commission' => $commissionRates['cash_commission'] ?? 20,
            'contact' => trim(implode(' | ', array_filter([$this->phone, $this->email]))),
        ];
    }
}
