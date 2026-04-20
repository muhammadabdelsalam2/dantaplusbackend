<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'paid_at' => optional($this->paid_at)?->toISOString(),
            'notes' => $this->notes,
            'recorded_by' => $this->recorder?->name,
        ], static fn ($value) => $value !== null);
    }
}
