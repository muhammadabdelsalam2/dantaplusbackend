<?php

namespace App\Repositories;

use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CommunicationConversationRepository
{
    public function paginateConversations(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return CommunicationConversation::query()
            ->with(['clinic:id,name', 'lab:id,name'])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q
                    ->where('is_read', false)
                    ->where('sender_type', '!=', 'super-admin'),
            ])
            ->when($filters['tab'] ?? null, fn (Builder $query, $tab) => $this->applyTabFilter($query, $tab))
            ->when($filters['clinic_id'] ?? null, fn (Builder $query, $clinicId) => $query->where('clinic_id', $clinicId))
            ->when($filters['lab_id'] ?? null, fn (Builder $query, $labId) => $query->where('lab_id', $labId))
            ->when($filters['search'] ?? null, function (Builder $query, $search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->whereHas('clinic', fn (Builder $clinic) => $clinic->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('lab', fn (Builder $lab) => $lab->where('name', 'like', "%{$search}%"))
                        ->orWhere('last_message_text', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateMessages(int $conversationId, int $perPage = 30): LengthAwarePaginator
    {
        return CommunicationMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findConversationById(int $id): ?CommunicationConversation
    {
        return CommunicationConversation::query()
            ->with(['clinic:id,name', 'lab:id,name'])
            ->find($id);
    }

    public function createMessage(array $data): CommunicationMessage
    {
        return CommunicationMessage::query()->create($data);
    }

    public function updateConversation(CommunicationConversation $conversation, array $data): CommunicationConversation
    {
        $conversation->update($data);

        return $conversation->refresh();
    }

    public function markIncomingAsRead(int $conversationId): void
    {
        CommunicationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_type', '!=', 'super-admin')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    public function markIncomingAsReadForUser(int $conversationId, ?int $viewerId): void
    {
        $query = CommunicationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('is_read', false);

        if ($viewerId) {
            $query->where(function ($q) use ($viewerId) {
                $q->whereNull('sender_id')->orWhere('sender_id', '!=', $viewerId);
            });
        }

        $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    private function applyTabFilter(Builder $query, string $tab): void
    {
        match ($tab) {
            'resolved' => $query->where('status', CommunicationConversation::STATUS_RESOLVED),

            'interventions' => $query->whereHas('messages', function (Builder $messages) {
                $messages->where('sender_type', 'super-admin')
                    ->where('sender_id', auth()->id());
            }),

            'all', null, '' => null,

            default => null,
        };
    }
}
