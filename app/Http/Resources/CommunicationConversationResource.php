<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinicId' => $this->clinic_id,
            'labId' => $this->lab_id,
            'contextType' => $this->context_type,
            'contextId' => $this->context_id,
            'status' => $this->status,
            'lastMessageText' => $this->last_message_text,
            'lastMessageAt' => optional($this->last_message_at)->toISOString(),
            'lastMessageSenderId' => $this->last_message_sender_id,
            'unreadCount' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
        ];
    }
}
