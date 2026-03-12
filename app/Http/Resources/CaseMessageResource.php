<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caseId' => $this->case_id,
            'senderId' => $this->sender_id,
            'senderName' => $this->sender_name,
            'senderType' => $this->sender_type,
            'message' => $this->message,
            'isInternal' => (bool) $this->is_internal,
            'isRead' => (bool) $this->is_read,
            'readAt' => optional($this->read_at)->toISOString(),
            'attachmentUrl' => $this->attachment_url,
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
