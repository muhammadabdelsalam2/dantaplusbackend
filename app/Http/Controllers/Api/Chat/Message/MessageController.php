<?php

namespace App\Http\Controllers\Api\Chat\Message;

use App\DTOs\Message\CreateMessageDTO;
use App\Http\Controllers\Controller;
use App\Services\Chat\MessageService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    //

    public function __construct(
        private MessageService $messageService
    ) {
    }

    public function index($chatId)
    {
        return response()->json(
            $this->messageService->getMessages($chatId)
        );
    }

    public function store(Request $request)
    {
        $dto = CreateMessageDTO::fromRequest($request);

        $message = $this->messageService->sendMessage($dto);
            dd($message);
        return response()->json([
            'status' => true,

            'message' => $message['data']
        ]);
    }
}
