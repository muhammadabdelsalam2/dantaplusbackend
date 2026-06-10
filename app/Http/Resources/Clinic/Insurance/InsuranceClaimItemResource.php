<?php

namespace App\Http\Resources\Clinic\Insurance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceClaimItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_name' => $this->service_name,
            'code' => $this->code,
            'category_name' => $this->category_name,
            'unit_price' => (float) $this->unit_price,
            'quantity' => $this->quantity,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
