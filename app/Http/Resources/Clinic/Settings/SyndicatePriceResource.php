<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyndicatePriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => (int) $this->year,
            'code' => $this->code,
            'service_name' => $this->service_name,
            'category' => $this->category,
            'price' => (float) $this->price,
            'is_active_year' => (bool) $this->is_active_year,
            'last_updated' => optional($this->updated_at)->toISOString(),
        ];
    }
}
