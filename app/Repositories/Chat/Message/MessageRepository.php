<?php

namespace App\Repositories\Chat\Message;

use App\Models\Message;
use App\DTOs\Message\CreateMessageDTO;
use App\Models\MessageChat;
use App\Repositories\Contracts\Chat\Message\MessageRepositoryInterface;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(CreateMessageDTO $dto)
    {
        return Message::create([
            'chat_id' => $dto->chatId,
            'sender_id' => $dto->senderId,
            'message' => $dto->message,
            'type' => $dto->type,
            'reply_to_id' => $dto->replyToId,
        ]);
    }

    public function getChatMessages(int $chatId)
    {
        return MessageChat::where('chat_id', $chatId)
            ->forTeamOwner(auth()->id())
            ->latest()
            ->get();
    }
}