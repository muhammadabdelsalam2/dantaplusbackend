<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicDentalLabAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this['summary'] ?? [],
            'orders_by_dental_lab' => $this['orders_by_dental_lab'] ?? [],
            'orders_by_provider' => collect($this['orders_by_dental_lab'] ?? [])
                ->map(fn (array $item) => [
                    'provider_id' => $item['dental_lab_id'] ?? null,
                    'provider_name' => $item['dental_lab_name'] ?? null,
                    'orders_count' => $item['orders_count'] ?? 0,
                ])
                ->values()
                ->all(),
            'delivery_time_trend' => $this['delivery_time_trend'] ?? [],
            'recent_orders' => ClinicDentalLabOrderResource::collection($this['recent_orders'] ?? [])->resolve(),
        ];
    }
}
