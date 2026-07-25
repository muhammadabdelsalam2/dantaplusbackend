<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender_name,
            'type' => in_array($this->related_type, ['invoice', 'case'], true) ? $this->related_type : ($this->message_type ?: $this->type),
            'message_type' => $this->message_type ?: $this->type,
            'content' => $this->content ?: $this->text,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'card' => $this->related_type ? [
                'type' => $this->related_type,
                'title' => $this->related_type === 'case' ? 'Case Details' : 'Invoice Sent',
                'id' => $this->content ? str_replace(['Invoice ', 'Case '], '', $this->content) : $this->related_id,
                'related_id' => $this->related_id,
            ] : null,
            'attachment_url' => $this->attachment_path ? asset('storage/' . $this->attachment_path) : ($this->attachment_url ?: null),
            'is_read' => (bool) $this->is_read,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
