<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsurancePriceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'year' => $this->year,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'imported_at' => optional($this->imported_at)?->toISOString(),
            'items_count' => $this->whenCounted('items'),
            'items' => InsurancePriceListItemResource::collection($this->whenLoaded('items')),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
