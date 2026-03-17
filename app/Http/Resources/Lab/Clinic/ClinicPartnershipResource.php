<?php

namespace App\Http\Resources\Lab\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicPartnershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->id ?? 0),
            'status' => $this->status?->value ?? ($this->status ?? ''),
            'partnership_start_date' => $this->partnership_start_date?->toDateString() ?? '',
            'total_cases_sent' => (int) ($this->total_cases_sent ?? 0),
            'last_case_date' => $this->last_case_date?->toDateString() ?? '',
            'clinic' => [
                'id' => (string) ($this->clinic?->id ?? ''),
                'name' => $this->clinic?->name ?? '',
                'email' => $this->clinic?->email ?? '',
            ],
        ];
    }
}
