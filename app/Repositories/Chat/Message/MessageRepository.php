<?php

namespace App\Repositories\Chat\Message;

use App\Models\Message;
use App\DTOs\CreateMessageDTO;

use App\Models\MessageChat;
use App\Repositories\Contracts\Chat\Message\MessageRepositoryInterface;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(CreateMessageDTO $dto): MessageChat
    {
        return MessageChat::create([
            'chat_id' => $dto->chat_id,
            'sender_id' => $dto->sender_id,
            'message' => $dto->message,
            'type' => $dto->type,
            'reply_to_id' => $dto->reply_to_id,
            'metadata' => $dto->metadata,
        ]);
    }

    public function getChatMessages(int $chatId, int $userId)
    {
        return MessageChat::query()
            ->where('chat_id', $chatId)

            // user must be participant in this chat
            ->whereHas('chat.participants', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })

            ->with(['sender']) // optional
            ->latest()
            ->get();
    }


    public function getmemberChatMessage()
    {

    }
}