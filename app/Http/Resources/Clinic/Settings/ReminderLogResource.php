<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient?->user?->name ?? $this->appointment?->patient_name,
            'appointment_id' => $this->clinic_appointment_id,
            'channel' => $this->channel,
            'template' => $this->template,
            'status' => $this->status,
            'triggered_at' => optional($this->triggered_at)?->toISOString(),
            'payload' => $this->payload,
        ];
    }
}
