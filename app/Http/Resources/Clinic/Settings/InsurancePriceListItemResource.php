<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsurancePriceListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'code' => $this->code ?? $this->item_code,
            'item_code' => $this->item_code,
            'service_name' => $this->service_name,
            'category' => $this->category?->name ?? $this->category_name,
            'category_id' => $this->category_id,
            'price' => $this->price !== null ? (float) $this->price : null,
            'notes' => $this->notes,
        ];
    }
}
