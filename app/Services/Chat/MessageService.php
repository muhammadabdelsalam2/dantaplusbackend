<?php


namespace App\Services\Chat;

// use App\DTOs\CreateMessageDTO;
use App\DTOs\SendMessageDTO;
use App\Events\MessageSent;
use App\Factories\Chat\Message\MessageFactory;
use App\Repositories\Chat\Message\MessageRepository;
use App\Repositories\Contracts\Chat\Message\MessageRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(
        private MessageRepository $messageRepository,
        private MessageFactory $messageFactory,
        private ServiceResult $serviceResult
    ) {
    }

    // public function sendMessage(CreateMessageDTO $dto)
    // {
    //     return DB::transaction(function () use ($dto) {

    //         // 1. Factory preprocessing
    //         $dto = $this->messageFactory->prepare($dto);

    //         // 2. Create message
    //         $message = $this->messageRepository->create($dto);

    //         // 3. Attach files
    //         if (!empty($dto->files)) {
    //             $this->messageFactory->storeFiles($message, $dto->files);
    //         }

    //         // 4. Extract mentions
    //         $this->messageFactory->handleMentions($message);

    //         return $message;
    //     });
    // }

    public function getMessages(int $chatId, $userId)
    {
        $chatMessages = $this->messageRepository->getChatMessages($chatId, $userId);
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
    public function getMemberMessages(int $chatId)
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
    public function sendMessage(SendMessageDTO $dto)
    {
        return DB::transaction(function () use ($dto) {

            // 🔐 security check
            $is_owner = $this->ensureUserInChat($dto->chat_id, $dto->sender_id);
            if (!$is_owner) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access to this chat.',
                    'error_code' => 'CHAT_ACCESS_DENIED'
                ], 403);
            }
            // 🏭 factory
            $createDTO = MessageFactory::make($dto);

            // 🗄️ save message
            $message = $this->messageRepository->create($createDTO);

            $message->load(['sender']);

            // 📡 REALTIME (ONLY THIS)
            broadcast(new MessageSent($message))->toOthers();

            return $message;
        });
    }

    protected function ensureUserInChat($chatId, $userId)
    {
        $isParticipant = DB::table('chat_participants')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->exists();

        $isOwner = DB::table('chats')
            ->join('teams', 'teams.id', '=', 'chats.team_id')
            ->where('chats.id', $chatId)
            ->where('teams.owner_id', $userId)
            ->exists();

        if (!$isParticipant && !$isOwner) {
            return false;
        }
    }
}