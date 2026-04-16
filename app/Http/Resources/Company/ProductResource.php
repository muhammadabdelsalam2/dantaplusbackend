<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : ($this->image_url ?: null),
            'category' => $this->categoryRelation ? [
                'id' => $this->categoryRelation->id,
                'name' => $this->categoryRelation->name,
            ] : null,
            'company' => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ] : null,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'status' => strtolower((string) $this->status),
            'description' => $this->description,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'rating' => (float) $this->rating,
            'review_count' => $this->review_count,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
