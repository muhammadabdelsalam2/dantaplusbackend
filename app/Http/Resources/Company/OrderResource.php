<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'delivery_address' => $this->delivery_address,
            'delivery_at' => optional($this->delivery_at)?->toISOString(),
            'total_amount' => (float) ($this->total_amount ?: $this->amount_total),
            'clinic_id' => $this->clinic?->id,
            'clinic_name' => $this->clinic?->name ?? $this->external_clinic_name,
            'clinic_phone' => $this->clinic?->phone ?? $this->external_clinic_phone,
            'clinic' => $this->clinic ? [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'email' => $this->clinic->email,
                'phone' => $this->clinic->phone,
            ] : null,
            'external_clinic' => [
                'name' => $this->external_clinic_name,
                'phone' => $this->external_clinic_phone,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'invoice_id' => $this->invoice?->id,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
