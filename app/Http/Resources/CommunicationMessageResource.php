<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $text = $this->text ?? $this->content;
        $displayText = $text;

        if (! $displayText && ($this->is_system_message || $this->type === 'system' || $this->message_type === 'invoice')) {
            $displayText = 'Invoice Sent';
        }

        return [
            'id' => $this->id,
            'conversationId' => $this->conversation_id,
            'conversation_id' => $this->conversation_id,
            'senderId' => $this->sender_id,
            'sender_id' => $this->sender_id,
            'senderName' => $this->sender_name,
            'sender_name' => $this->sender_name,
            'senderType' => $this->sender_type,
            'sender_type' => $this->sender_type,
            'sender_role' => $this->sender_type,
            'text' => $displayText,
            'content' => $displayText,
            'type' => $this->type,
            'message_type' => $this->message_type ?? $this->type,
            'relatedId' => $this->related_id,
            'related_id' => $this->related_id,
            'related_type' => $this->related_type,
            'attachmentUrl' => $this->attachment_url,
            'attachment_url' => $this->attachment_url,
            'attachment_path' => $this->attachment_path,
            'attachment_name' => $this->attachment_name,
            'isSystemMessage' => (bool) $this->is_system_message,
            'is_system_message' => (bool) $this->is_system_message,
            'isRead' => (bool) $this->is_read,
            'is_read' => (bool) $this->is_read,
            'readAt' => optional($this->read_at)->toISOString(),
            'read_at' => optional($this->read_at)->toISOString(),
            'createdAt' => optional($this->created_at)->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
            'date' => optional($this->created_at)->toDateString(),
            'time' => optional($this->created_at)->format('H:i'),
        ];
    }
}
