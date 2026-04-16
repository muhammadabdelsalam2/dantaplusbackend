<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_name' => $this->zone_name,
            'shipping_cost' => (float) $this->shipping_cost,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'polygon_coordinates' => $this->polygon_coordinates,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
        ];
    }
}
