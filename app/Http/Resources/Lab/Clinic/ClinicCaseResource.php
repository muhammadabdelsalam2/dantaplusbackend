<?php

namespace App\Http\Resources\Lab\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->case_number ?? (string) ($this->id ?? ''),
            'patient' => [
                'name' => $this->patient?->user?->name ?? '',
            ],
            'due_date' => $this->due_date?->toDateString() ?? '',
            'status' => $this->status ?? '',
        ];
    }
}
