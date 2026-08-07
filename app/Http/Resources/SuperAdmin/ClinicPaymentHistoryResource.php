<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicPaymentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'status' => $this->paid_at ? 'Paid' : 'Pending',
            'payment_date' => optional($this->paid_at ?? $this->created_at)->toISOString(),
            'payment_method' => $this->method,
        ];
    }
}
