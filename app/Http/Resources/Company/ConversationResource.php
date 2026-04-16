<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'clinic' => $this->clinic ? [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'email' => $this->clinic->email,
                'phone' => $this->clinic->phone,
            ] : null,
            'last_message_text' => $this->last_message_text,
            'last_message_at' => optional($this->last_message_at)?->toISOString(),
            'files_count' => $this->whenCounted('files'),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
