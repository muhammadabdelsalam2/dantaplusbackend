<?php

namespace App\Services\Communication;

use App\Http\Resources\CommunicationConversationResource;
use App\Http\Resources\CommunicationMessageResource;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Repositories\CommunicationConversationRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function __construct(private CommunicationConversationRepository $conversationRepository) {}

    public function listContacts(array $filters): array
    {
        $user = auth()->user();

        if (! $user?->clinic_id && ! $user?->lab_id) {
            return ServiceResult::error('No clinic or lab linked to this account', null, null, 403);
        }

        $filters['clinic_id'] = $filters['clinic_id'] ?? $user?->clinic_id;
        $filters['lab_id'] = $filters['lab_id'] ?? $user?->lab_id;

        $perPage = (int) ($filters['per_page'] ?? 20);
        $conversations = $this->conversationRepository->paginateConversations($filters, $perPage);

        return ServiceResult::success([
            'items' => CommunicationConversationResource::collection($conversations->items())->resolve(),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ], 'Contacts fetched successfully');
    }

    public function listMessages(int $conversationId, int $perPage = 30): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $messages = $this->conversationRepository->paginateMessages($conversationId, $perPage);
        $this->conversationRepository->markIncomingAsReadForUser($conversationId, auth()->id());

        return ServiceResult::success([
            'items' => CommunicationMessageResource::collection($messages->items())->resolve(),
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
            if (! $conversation || ! $this->canAccessConversation($conversation)) {
                return ServiceResult::error('Conversation not found', null, null, 404);
            }

            $sender = auth()->user();

            $message = $this->conversationRepository->createMessage([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender?->id,
                'sender_name' => $data['sender_name'] ?? $sender?->name,
                'sender_type' => $data['sender_type'] ?? $this->resolveSenderType($conversation),
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

            return ServiceResult::success(
                (new CommunicationMessageResource($message))->resolve(),
                'Message sent successfully',
                201
            );
        });
    }

    public function updateConversationStatus(int $conversationId, array $data): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $updated = $this->conversationRepository->updateConversation($conversation, [
            'status' => $data['status'],
        ]);

        return ServiceResult::success(
            (new CommunicationConversationResource($updated))->resolve(),
            'Conversation status updated successfully'
        );
    }

    public function markRead(int $conversationId): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $this->conversationRepository->markIncomingAsReadForUser($conversationId, auth()->id());

        return ServiceResult::success(null, 'Conversation marked as read');
    }

    private function canAccessConversation(CommunicationConversation $conversation): bool
    {
        $user = auth()->user();

        if ($user?->lab_id && (int) $conversation->lab_id === (int) $user->lab_id) {
            return true;
        }

        if ($user?->clinic_id && (int) $conversation->clinic_id === (int) $user->clinic_id) {
            return true;
        }

        return false;
    }

    private function resolveSenderType(CommunicationConversation $conversation): string
    {
        $user = auth()->user();

        if ($user?->lab_id && (int) $conversation->lab_id === (int) $user->lab_id) {
            return 'lab';
        }

        if ($user?->clinic_id && (int) $conversation->clinic_id === (int) $user->clinic_id) {
            return 'clinic';
        }

        return 'user';
    }
}
