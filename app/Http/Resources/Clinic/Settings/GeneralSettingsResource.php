<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'working_hours_from' => $this['working_hours_from'] ?? '09:00',
            'working_hours_to' => $this['working_hours_to'] ?? '17:00',
            'days_off' => $this['days_off'] ?? [],
            'currency' => $this['currency'] ?? 'EGP',
            'currency_options' => ['USD', 'EGP', 'SAR'],
            'date_format' => $this['date_format'] ?? 'DD/MM/YYYY',
            'date_format_options' => ['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD'],
            'online_booking_enabled' => (bool) ($this['online_booking_enabled'] ?? false),
            'logo' => $this['logo'] ?? null,
        ];
    }
}
