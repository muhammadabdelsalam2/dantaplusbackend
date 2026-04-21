<?php


namespace App\DTOs\Message;

class CreateMessageDTO
{
    public function __construct(
        public readonly int $chatId,
        public readonly int $senderId,
        public readonly string $message,
        public readonly string $type = 'text',
    ) {
    }

    public static function fromRequest($request): self
    {
        return new self(
            chatId: $request->chat_id,
            senderId: auth()->id(),
            message: $request->message,
            type: $request->type ?? 'text',
        );
    }
}