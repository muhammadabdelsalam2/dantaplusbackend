<?php

namespace App\Http\Resources\Lab\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->resource['provider'] ?? null,
            'webhook_url' => $this->resource['webhook_url'] ?? null,
            'connection_status' => $this->resource['connection_status'] ?? null,
            'last_message_at' => $this->resource['last_message_at'] ?? null,
            'total_sent_24h' => $this->resource['total_sent_24h'] ?? null,
            'meta' => $this->resource['meta'] ?? null,
            'twilio' => $this->resource['twilio'] ?? null,
        ];
    }
}
