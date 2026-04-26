<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecuritySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enable_2fa' => (bool) $this->enable_2fa,
            'backup_schedule' => $this->backup_schedule ?? 'daily',
            'retention_days' => (int) ($this->retention_days ?? 3650),
            'api_keys' => [
                'implemented' => false,
                'message' => 'TODO: API keys module is not implemented yet.',
            ],
            'access_logs' => [
                'implemented' => false,
                'message' => 'TODO: Access logs module is not implemented yet.',
            ],
        ];
    }
}
