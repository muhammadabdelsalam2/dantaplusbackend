<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'connected' => (bool) $this->connected,
            'access_token' => $this->access_token ? '********' : null,
            'refresh_token' => $this->refresh_token ? '********' : null,
        ];
    }
}
