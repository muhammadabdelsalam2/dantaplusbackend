<?php
namespace App\DTOs;


class SendMessageDTO
{
    public function __construct(
        public int $chat_id,
        public int $sender_id,
        public ?string $message,
        public string $type,
        public ?int $reply_to_id = null,
        public ?array $metadata = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            chat_id: $data['chat_id'],
            sender_id: $data['sender_id'],
            message: $data['message'] ?? null,
            type: $data['type'],
            reply_to_id: $data['reply_to_id'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}