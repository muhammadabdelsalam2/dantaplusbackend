<?php


namespace App\Factories\Chat\Message;

use App\DTOs\CreateMessageDTO;
use App\DTOs\SendMessageDTO;

class MessageFactory
{
    public static function make(SendMessageDTO $dto): CreateMessageDTO
    {
        return new CreateMessageDTO(
            chat_id: $dto->chat_id,
            sender_id: $dto->sender_id,
            message: $dto->message,
            type: $dto->type,
            reply_to_id: $dto->reply_to_id,
            metadata: $dto->metadata,
        );
    }
}