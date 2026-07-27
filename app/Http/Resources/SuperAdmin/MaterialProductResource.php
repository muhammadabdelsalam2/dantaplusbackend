<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'image_url' => $this->image_url ?? null,
            'brand' => $this->brand,
            'name' => $this->name,
            'description' => $this->description,
            'stock' => (int) $this->stock,
            'price' => (float) $this->price,
            'category' => $this->category,
            'status' => $this->status,
        ];
    }
}
