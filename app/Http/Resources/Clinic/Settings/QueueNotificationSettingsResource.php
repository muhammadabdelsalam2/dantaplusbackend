<?php

namespace App\Http\Resources\Clinic\Settings;

use App\Enums\WhatsAppProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueNotificationSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled' => (bool) $this->is_enabled,
            'notify_next' => (int) ($this->notify_next ?? 3),
            'whatsapp_provider' => $this->whatsapp_provider ?? WhatsAppProvider::TwilioWhatsAppApi->value,
            'message_template' => $this->message_template
                ?? 'Dear {PatientName}, there are only {numberBefore} patients before you at {ClinicName}.',
            'placeholders' => ['{PatientName}', '{numberBefore}', '{ClinicName}', '{AppointmentTime}'],
        ];
    }
}
