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
            'company_id' => $this->company_id,
            'clinic_id' => $this->clinic_id,
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'barcode' => $this->barcode,
            'product_name' => $this->product_name,
            'category_name' => $this->category_name,
            'description' => $this->description,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'quantity' => $this->quantity,
            'minimum_stock_level' => $this->minimum_stock_level,
            'reorder_quantity' => $this->reorder_quantity,
            'unit' => $this->unit,
            'consumption_per_case' => $this->consumption_per_case !== null ? (float) $this->consumption_per_case : null,
            'auto_purchase' => (bool) $this->auto_purchase,
            'supplier' => $this->supplier,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'total_value' => (float) ($this->quantity * (float) $this->unit_price),
            'status' => $this->status,
            'is_low_stock' => $this->quantity <= $this->minimum_stock_level,
            'last_updated_at' => optional($this->last_updated_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
