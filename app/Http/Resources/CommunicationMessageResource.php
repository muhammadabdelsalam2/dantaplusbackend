<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversationId' => $this->conversation_id,
            'senderId' => $this->sender_id,
            'senderName' => $this->sender_name,
            'senderType' => $this->sender_type,
            'text' => $this->text,
            'type' => $this->type,
            'relatedId' => $this->related_id,
            'attachmentUrl' => $this->attachment_url,
            'isSystemMessage' => (bool) $this->is_system_message,
            'isRead' => (bool) $this->is_read,
            'readAt' => optional($this->read_at)->toISOString(),
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
