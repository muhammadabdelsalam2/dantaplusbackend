<?php


namespace App\Services\Chat;

use App\DTOs\Message\CreateMessageDTO;
use App\Factories\Chat\Message\MessageFactory;
use App\Repositories\Chat\Message\MessageRepository;
use App\Repositories\Contracts\Chat\Message\MessageRepositoryInterface;
use App\Support\ServiceResult;

class MessageService
{
    public function __construct(
        private MessageRepository $messageRepository,
        private MessageFactory $messageFactory,
        private ServiceResult $serviceResult
    ) {
    }

    public function sendMessage(CreateMessageDTO $dto)
    {
        return DB::transaction(function () use ($dto) {

            // 1. Factory preprocessing
            $dto = $this->messageFactory->prepare($dto);

            // 2. Create message
            $message = $this->messageRepository->create($dto);

            // 3. Attach files
            if (!empty($dto->files)) {
                $this->messageFactory->storeFiles($message, $dto->files);
            }

            // 4. Extract mentions
            $this->messageFactory->handleMentions($message);

            return $message;
        });
    }

    public function getMessages(int $chatId)
    {
        $chatMessages = $this->messageRepository->getChatMessages($chatId);
        if (!$chatMessages) {
            return $this->serviceResult->error(
                message: 'Messages fetched Faild',
                nextEndpoint: null,
                errors: [],
                code: 204,
            );
        }
        return $this->serviceResult->success(
            $chatMessages,
            'Messages fetched successfully'
        );


    }
}