<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient?->user?->name ?? $this->appointment?->patient_name,
            'appointment_id' => $this->clinic_appointment_id,
            'channel' => $this->channel,
            'status' => $this->status,
            'scheduled_for' => optional($this->scheduled_for)?->toISOString(),
            'sent_at' => optional($this->sent_at)?->toISOString(),
            'feedback_link' => $this->feedback_link,
            'rendered_message' => $this->rendered_message,
            'payload' => $this->payload,
        ];
    }
}
