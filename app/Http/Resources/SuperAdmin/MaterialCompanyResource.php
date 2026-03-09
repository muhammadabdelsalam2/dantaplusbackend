<?php

namespace App\Http\Resources\SuperAdmin;

use App\Http\Resources\SuperAdmin\MaterialProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MaterialCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'commission_percentage' => $this->commission_percentage,
            'logo_url' => $this->logo_url,
            'description' => $this->description,
            'phone' => $this->phone,
            'website' => $this->website,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,
            'categories' => $this->mapCategories($this->categories),
            'status' => $this->status,
            'is_featured' => (bool) $this->is_featured,
            'rating' => $this->rating,
            'last_commission_update' => $this->last_commission_update,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'total_products' => $this->whenCounted('products'),
            'total_requests' => $this->when(isset($this->total_requests), $this->total_requests),
            'completed_requests' => $this->when(isset($this->completed_requests), $this->completed_requests),

            'products' => MaterialProductResource::collection($this->whenLoaded('products')),
        ];
    }

    private function mapCategories($categories): array
    {
        $configCategories = collect(config('material_market.company_category_items', []));

        return collect($categories ?? [])
            ->map(function ($categoryKey) use ($configCategories) {
                $matched = $configCategories->firstWhere('key', $categoryKey);

                return [
                    'key' => $categoryKey,
                    'label' => $matched['label'] ?? Str::headline($categoryKey),
                ];
            })
            ->values()
            ->toArray();
    }
}
