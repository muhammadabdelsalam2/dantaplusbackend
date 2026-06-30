<?php

namespace App\Http\Resources\Lab\Material;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expirationDate = $this->expiration_date;
        $purchaseDate = $this->purchase_date;

        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'name' => $this->name,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier,
            'supplier_name' => $this->relationLoaded('supplierCompany') && $this->supplierCompany ? $this->supplierCompany->name : $this->supplier,
            'supplier_object' => $this->whenLoaded('supplierCompany', fn () => [
                'id' => $this->supplierCompany->id,
                'name' => $this->supplierCompany->name,
            ]),
            'stock' => $this->stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'cost' => $this->cost,
            'purchase_date' => $purchaseDate?->toDateString(),
            'expiration_date' => $expirationDate?->toDateString(),
            'is_low_stock' => $this->stock <= $this->low_stock_threshold,
            'is_expired' => $expirationDate ? $expirationDate->isBefore(now()->startOfDay()) : false,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
