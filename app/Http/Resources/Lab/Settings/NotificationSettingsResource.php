<?php

namespace App\Http\Resources\Lab\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'new_case_alerts' => $this->resource['new_case_alerts'] ?? null,
            'case_update_alerts' => $this->resource['case_update_alerts'] ?? null,
        ];
    }
}
