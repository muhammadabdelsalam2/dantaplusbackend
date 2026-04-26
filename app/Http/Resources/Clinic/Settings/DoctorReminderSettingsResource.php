<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorReminderSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled' => (bool) $this->is_enabled,
            'send_time' => $this->send_time ?? '20:00',
            'channels' => $this->channels ?: ['sms', 'whatsapp'],
            'message_template' => $this->message_template
                ?? "Hello Dr. {DoctorName},\nHere is your schedule for tomorrow ({Date}):\n{AppointmentList}\n\n- {ClinicName}",
            'placeholders' => ['{DoctorName}', '{Date}', '{AppointmentList}', '{ClinicName}'],
        ];
    }
}
