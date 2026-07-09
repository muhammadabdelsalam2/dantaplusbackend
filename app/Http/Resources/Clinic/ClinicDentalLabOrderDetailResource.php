<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicDentalLabOrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'status' => $this->status,
            'priority' => $this->priority,
            'case_type' => $this->case_type,
            'tooth_numbers' => $this->tooth_numbers,
            'description' => $this->description,
            'date_sent' => optional($this->created_at)->toISOString(),
            'expected_delivery' => optional($this->due_date)?->toDateString(),
            'actual_delivery' => optional($this->delivered_at)?->toDateString(),
            'patient' => $this->patient ? [
                'id' => $this->patient->id,
                'name' => $this->patient->user?->name,
                'phone' => $this->patient->phone,
                'file_number' => $this->patient->patient_number,
            ] : null,
            'lab' => $this->lab ? [
                'id' => $this->lab->id,
                'name' => $this->lab->name,
                'contact_person' => $this->lab->contact_person,
                'phone' => $this->lab->phone,
                'address' => $this->lab->address,
                'email' => $this->lab->email,
            ] : null,
            'dentist' => $this->whenLoaded('dentist', fn () => [
                'id' => $this->dentist?->id,
                'name' => $this->dentist?->user?->name,
            ]),
            'delivery' => [
                'assigned_delivery_id' => $this->assigned_delivery_id,
                'latest_delivery_task' => $this->whenLoaded('latestDeliveryTask', fn () => [
                    'id' => $this->latestDeliveryTask?->id,
                    'status' => $this->latestDeliveryTask?->status,
                    'scheduled_for' => optional($this->latestDeliveryTask?->scheduled_for)?->toISOString(),
                    'assigned_at' => optional($this->latestDeliveryTask?->assigned_at)?->toISOString(),
                    'picked_up_at' => optional($this->latestDeliveryTask?->picked_up_at)?->toISOString(),
                    'delivered_at' => optional($this->latestDeliveryTask?->delivered_at)?->toISOString(),
                    'pickup_address' => $this->latestDeliveryTask?->pickup_address,
                    'delivery_address' => $this->latestDeliveryTask?->delivery_address,
                    'delivery_notes' => $this->latestDeliveryTask?->delivery_notes,
                    'delivery_rep' => $this->latestDeliveryTask?->deliveryRep ? [
                        'id' => $this->latestDeliveryTask->deliveryRep->id,
                        'name' => $this->latestDeliveryTask->deliveryRep->name,
                    ] : null,
                ]),
            ],
        ];
    }
}
