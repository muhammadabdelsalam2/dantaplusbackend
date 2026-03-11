<?php

namespace App\Services\Owner;

use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Repositories\CommunicationConversationRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class CommunicationCenterService
{
    public function __construct(private CommunicationConversationRepository $conversationRepository) {}

public function listConversations(array $filters): array
{
    $perPage = (int) ($filters['per_page'] ?? 10);
    $conversations = $this->conversationRepository->paginateConversations($filters, $perPage);

    $items = collect($conversations->items())->map(function (CommunicationConversation $conversation) {
        return [
            'id' => $conversation->id,
            'clinicId' => $conversation->clinic_id,
            'labId' => $conversation->lab_id,
            'name' => ($conversation->clinic?->name ?? 'Unknown Clinic') . ' ↔ ' . ($conversation->lab?->name ?? 'Unknown Lab'),
            'avatarUrl' => null,
            'lastMessageText' => $conversation->last_message_text,
            'lastMessageTimestamp' => optional($conversation->last_message_at)?->toISOString(),
            'unreadCount' => (int) ($conversation->unread_count ?? 0),
            'status' => $conversation->status,
        ];
    })->values()->all();

    return ServiceResult::success([
        'items' => $items,
        'pagination' => [
            'current_page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
            'per_page' => $conversations->perPage(),
            'total' => $conversations->total(),
        ],
    ], 'Conversations fetched successfully');
}
    public function listMessages(int $conversationId, int $perPage = 30): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);

        if (! $conversation) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $messages = $this->conversationRepository->paginateMessages($conversationId, $perPage);
        $this->conversationRepository->markIncomingAsRead($conversationId);

        $items = collect($messages->items())->map(fn (CommunicationMessage $message) => $this->mapMessage($message))->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ], 'Conversation messages fetched successfully');
    }

    public function sendMessage(int $conversationId, array $data): array
    {
        return DB::transaction(function () use ($conversationId, $data) {
            $conversation = $this->conversationRepository->findConversationById($conversationId);

            if (! $conversation) {
                return ServiceResult::error('Conversation not found', null, null, 404);
            }

            $sender = auth()->user();

            $message = $this->conversationRepository->createMessage([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender?->id,
                'sender_name' => $data['sender_name'] ?? $sender?->name,
                'sender_type' => $data['sender_type'] ?? 'super-admin',
                'text' => $data['text'] ?? null,
                'type' => $data['type'] ?? CommunicationMessage::TYPE_TEXT,
                'related_id' => $data['related_id'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'is_system_message' => (bool) ($data['is_system_message'] ?? false),
                'is_read' => false,
            ]);

            $this->conversationRepository->updateConversation($conversation, [
                'last_message_text' => $message->text ?: 'Attachment',
                'last_message_at' => now(),
                'last_message_sender_id' => $sender?->id,
            ]);

            return ServiceResult::success($this->mapMessage($message), 'Message sent successfully', 201);
        });
    }

    public function updateConversation(int $conversationId, array $data): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);

        if (! $conversation) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $updated = $this->conversationRepository->updateConversation($conversation, $data);

        return ServiceResult::success([
            'id' => $updated->id,
            'clinicId' => $updated->clinic_id,
            'labId' => $updated->lab_id,
            'status' => $updated->status,
            'lastMessageText' => $updated->last_message_text,
            'lastMessageTimestamp' => optional($updated->last_message_at)->toISOString(),
        ], 'Conversation updated successfully');
    }

 public function analytics(): array
{
    $totals = [
        'total_conversations' => CommunicationConversation::query()->count(),
        'active_conversations' => CommunicationConversation::query()
            ->where('status', CommunicationConversation::STATUS_ACTIVE)
            ->count(),
        'resolved_conversations' => CommunicationConversation::query()
            ->where('status', CommunicationConversation::STATUS_RESOLVED)
            ->count(),
        'escalated_conversations' => CommunicationConversation::query()
            ->where('status', CommunicationConversation::STATUS_ESCALATED)
            ->count(),
        'my_interventions' => CommunicationMessage::query()
            ->where('sender_id', auth()->id())
            ->where('sender_type', 'super-admin')
            ->count(),
        'unread_messages' => CommunicationMessage::query()
            ->where('sender_type', '!=', 'super-admin')
            ->where('is_read', false)
            ->count(),
    ];

    return ServiceResult::success($totals, 'Communication analytics fetched successfully');
}

    private function mapMessage(CommunicationMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversationId' => $message->conversation_id,
            'senderId' => $message->sender_id,
            'senderName' => $message->sender_name,
            'senderType' => $message->sender_type,
            'text' => $message->text,
            'type' => $message->type,
            'relatedId' => $message->related_id,
            'timestamp' => optional($message->created_at)->toISOString(),
            'read' => (bool) $message->is_read,
            'isSystemMessage' => (bool) $message->is_system_message,
            'attachmentUrl' => $message->attachment_url ? asset($message->attachment_url) : null,
        ];
    }
}
