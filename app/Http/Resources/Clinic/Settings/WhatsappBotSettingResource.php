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
            'welcome_message' => $this->welcome_message,
            'out_of_hours_message' => $this->out_of_hours_message,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'language' => $this->language,
            'require_deposit' => (bool) $this->require_deposit,
            'deposit_amount' => $this->deposit_amount !== null ? (float) $this->deposit_amount : null,
            'allowed_services' => $this->allowed_services ?? [],
            'ai_enabled' => (bool) $this->ai_enabled,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
