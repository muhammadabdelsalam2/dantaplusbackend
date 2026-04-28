<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ClinicDentalLabResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $partnership = $this->partnerships instanceof Collection ? $this->partnerships->first() : null;
        $orders = collect($this->cases ?? []);
        $deliveredOrders = $orders->where('status', 'Delivered');
        $lateOrders = $orders->filter(
            fn ($order) => $order->status !== 'Delivered' && $order->due_date && now()->toDateString() > $order->due_date->toDateString()
        );
        $avgDeliveryDays = round(
            (float) ($deliveredOrders
                ->filter(fn ($order) => $order->created_at && $order->delivered_at)
                ->avg(fn ($order) => $order->created_at->diffInDays($order->delivered_at)) ?? ($this->avg_delivery_days ?? 0)),
            2
        );
        $onTimeCount = $deliveredOrders->filter(
            fn ($order) => $order->delivered_at && $order->due_date && $order->delivered_at->toDateString() <= $order->due_date->toDateString()
        )->count();
        $onTimeRate = $deliveredOrders->count() > 0
            ? round(($onTimeCount / $deliveredOrders->count()) * 100, 2)
            : (float) ($this->on_time_percentage ?? 0);

        return [
            'id' => $this->id,
            'clinic_id' => $partnership?->clinic_id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'avg_delivery_days' => $avgDeliveryDays,
            'avg_delivery_time' => (int) round($avgDeliveryDays),
            'response_speed' => $this->response_speed,
            'working_hours' => $this->working_hours,
            'status' => $this->status,
            'partnership_status' => $partnership?->status?->value ?? $partnership?->status,
            'on_time_percentage' => $onTimeRate,
            'on_time_rate' => $onTimeRate,
            'late_deliveries' => $lateOrders->count(),
            'late_orders' => $lateOrders->count(),
            'total_cases_sent' => (int) ($partnership?->total_cases_sent ?? $orders->count()),
            'last_case_date' => optional($partnership?->last_case_date)->toDateString(),
            'services' => ClinicDentalLabServiceResource::collection($this->whenLoaded('labServices')),
            'gallery' => ClinicDentalLabGalleryResource::collection($this->whenLoaded('galleryImages')),
            'orders' => ClinicDentalLabOrderResource::collection($this->whenLoaded('cases')),
            'orders_count' => $orders->count(),
        ];
    }
}
