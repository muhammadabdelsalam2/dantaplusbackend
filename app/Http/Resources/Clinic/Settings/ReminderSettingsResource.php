<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timing = $this['timing'] ?? $this['times'] ?? ['24h'];

        return [
            'enabled' => (bool) ($this['enabled'] ?? false),
            'timing' => $timing,
            'times' => $this['times'] ?? $timing,
            'channel' => $this['channel'] ?? 'whatsapp',
            'template' => $this['template'] ?? 'Reminder: you have an appointment on :date at :time.',
        ];
    }
}
