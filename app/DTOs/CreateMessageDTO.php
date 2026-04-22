<?php


namespace App\DTOs;

class CreateMessageDTO
{
    public function __construct(
        public int $chat_id,
        public int $sender_id,
        public ?string $message,
        public string $type,
        public ?int $reply_to_id,
        public ?array $metadata,
    ) {
    }
}