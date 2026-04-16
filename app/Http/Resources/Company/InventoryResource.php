<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'category_name' => $this->category_name,
            'description' => $this->description,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'quantity' => $this->quantity,
            'minimum_stock_level' => $this->minimum_stock_level,
            'unit' => $this->unit,
            'supplier' => $this->supplier,
            'status' => $this->status,
            'is_low_stock' => $this->quantity <= $this->minimum_stock_level,
            'last_updated_at' => optional($this->last_updated_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
