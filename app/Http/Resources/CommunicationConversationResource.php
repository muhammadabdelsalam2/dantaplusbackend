<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clinicName = $this->clinic?->name ?? '';
        $lastMessageText = $this->last_message_text ?: 'No messages yet';

        return [
            'id' => $this->id,
            'clinicId' => $this->clinic_id,
            'labId' => $this->lab_id,
            'clinic_id' => $this->clinic_id,
            'lab_id' => $this->lab_id,
            'clinic' => $this->clinic ? [
                'id' => $this->clinic->id,
                'name' => $clinicName,
                'initials' => $this->initials($clinicName),
                'avatar' => null,
                'email' => $this->clinic->email ?? null,
                'phone' => $this->clinic->phone ?? null,
                'address' => $this->clinic->address ?? null,
            ] : null,
            'contact' => $this->clinic ? [
                'id' => $this->clinic->id,
                'name' => $clinicName,
                'initials' => $this->initials($clinicName),
                'avatar' => null,
            ] : null,
            'contextType' => $this->context_type,
            'contextId' => $this->context_id,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'status' => $this->status,
            'lastMessageText' => $this->last_message_text,
            'last_message_text' => $this->last_message_text,
            'lastMessageAt' => optional($this->last_message_at)->toISOString(),
            'last_message_at' => optional($this->last_message_at)->toISOString(),
            'lastMessageSenderId' => $this->last_message_sender_id,
            'last_message_sender_id' => $this->last_message_sender_id,
            'last_message' => [
                'text' => $lastMessageText,
                'preview' => $lastMessageText,
                'sent_at' => optional($this->last_message_at)->toISOString(),
                'time' => optional($this->last_message_at)->format('H:i'),
                'date' => optional($this->last_message_at)->toDateString(),
            ],
            'unreadCount' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
        ];
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($words)
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_substr($word, 0, 1))
            ->implode('');

        return mb_strtoupper($initials ?: 'NA');
    }
}
