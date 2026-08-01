<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappBotSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'is_enabled' => (bool) $this->is_enabled,
            'enabled' => (bool) $this->is_enabled,
            'welcome_message' => $this->welcome_message,
            'out_of_hours_message' => $this->out_of_hours_message,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'bot_working_hours' => [
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
            ],
            'language' => $this->language,
            'language_preference' => match ($this->language) {
                'ar' => 'Arabic',
                'en' => 'English',
                default => 'Auto-detect (English/Arabic)',
            },
            'require_deposit' => (bool) $this->require_deposit,
            'payment_requirement' => (bool) $this->require_deposit ? 'Require Deposit' : 'None',
            'deposit_amount' => $this->deposit_amount !== null ? (float) $this->deposit_amount : null,
            'allowed_services' => $this->allowed_services ?? [],
            'allowed_services_for_bot_booking' => $this->allowed_services ?? [],
            'available_services_for_bot_booking' => $this->additional['available_services_for_bot_booking'] ?? [],
            'ai_enabled' => (bool) $this->ai_enabled,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
