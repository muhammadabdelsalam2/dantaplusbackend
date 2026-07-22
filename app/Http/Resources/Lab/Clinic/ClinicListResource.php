<?php

namespace App\Http\Resources\Lab\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $partnership = $this->labPartnerships?->first();

        return [
            'id' => (string) ($this->id ?? ''),
            'name' => $this->name ?? '',
            'owner_name' => $this->owner_name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'address' => $this->address ?? '',
            'subdomain' => $this->subdomain ?? '',
            'type' => (bool) ($this->is_external ?? false) ? 'External' : 'Internal',
            'clinic_type' => $this->clinic_type?->value ?? '',
            'partnership' => [
                'id' => (int) ($partnership?->id ?? 0),
                'status' => $partnership?->status?->value ?? ($partnership?->status ?? ''),
                'partnership_start_date' => $partnership?->partnership_start_date?->toDateString() ?? '',
                'total_cases_sent' => (int) ($partnership?->total_cases_sent ?? 0),
                'last_case_date' => $partnership?->last_case_date?->toDateString() ?? '',
            ],
        ];
    }
}
