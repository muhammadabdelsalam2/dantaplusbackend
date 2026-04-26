<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePricingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $override = $this->relationLoaded('clinicPrices')
            ? $this->clinicPrices->first()
            : null;
        $basePrice = $this->base_price !== null ? (float) $this->base_price : null;
        $clinicPrice = $override?->price !== null ? (float) $override->price : null;
        $cost = $override?->cost !== null ? (float) $override->cost : 0.0;
        $labCost = $override?->lab_cost !== null ? (float) $override->lab_cost : 0.0;
        $hasLab = (bool) ($override?->has_lab ?? false);

        return [
            'id' => $this->id,
            'service_id' => $this->id,
            'override_id' => $override?->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category?->name,
            'category_id' => $this->category?->id,
            'category_details' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'is_base' => (bool) $this->is_base,
            'base_price' => $basePrice,
            'clinic_price' => $clinicPrice,
            'override_price' => $clinicPrice,
            'price' => $clinicPrice ?? $basePrice ?? 0,
            'cost' => $cost,
            'lab_cost' => $labCost,
            'has_lab' => $hasLab,
            'has_override' => (bool) $override,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
