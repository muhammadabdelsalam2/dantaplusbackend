<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $defaultDuration = (int) ($this['default_duration'] ?? $this['slot_duration'] ?? 30);
        $allowOverlap = (bool) ($this['allow_overlap'] ?? $this['allow_overbooking'] ?? false);
        $cancellationWindowHours = (int) ($this['cancellation_window_hours'] ?? 24);
        $cancellationPolicy = $this['cancellation_policy'] ?? ($cancellationWindowHours . 'h_notice');

        return [
            'default_duration' => $defaultDuration,
            'slot_duration' => (int) ($this['slot_duration'] ?? $defaultDuration),
            'buffer_time' => (int) ($this['buffer_time'] ?? 0),
            'max_advance_days' => (int) ($this['max_advance_days'] ?? 30),
            'allow_overlap' => $allowOverlap,
            'allow_overbooking' => (bool) ($this['allow_overbooking'] ?? $allowOverlap),
            'auto_confirm' => (bool) ($this['auto_confirm'] ?? false),
            'send_reminders' => (bool) ($this['send_reminders'] ?? true),
            'cancellation_policy' => $cancellationPolicy,
            'cancellation_window_hours' => $cancellationWindowHours,
        ];
    }
}
