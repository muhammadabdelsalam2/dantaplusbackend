<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled' => (bool) $this->is_enabled,
            'channels' => $this->channels ?: ['sms'],
            'delay_minutes' => (int) ($this->delay_minutes ?? 5),
            'message_template' => $this->message_template
                ?? "Hello {PatientName},\nThank you for visiting {ClinicName}! Please share your feedback here: {FeedbackLink}",
            'custom_link' => $this->custom_link,
            'placeholders' => ['{PatientName}', '{ClinicName}', '{FeedbackLink}'],
        ];
    }
}
