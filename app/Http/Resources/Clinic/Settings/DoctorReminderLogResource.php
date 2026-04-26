<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorReminderLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor' => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ] : null,
            'channel' => $this->channel,
            'status' => $this->status,
            'reminder_date' => optional($this->reminder_date)?->toDateString(),
            'triggered_at' => optional($this->triggered_at)?->toISOString(),
            'rendered_message' => $this->rendered_message,
            'payload' => $this->payload,
        ];
    }
}
