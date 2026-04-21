<?php

namespace App\Repositories\Contracts\Chat\Message;

use App\DTOs\Message\CreateMessageDTO;

interface MessageRepositoryInterface
{
    public function create(CreateMessageDTO $dto);
    public function getChatMessages(int $chatId);
}