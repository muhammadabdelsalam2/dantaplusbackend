<?php

namespace App\Http\Controllers\Api\Chat\Message;

// use App\DTOs\Message\CreateMessageDTO;
use App\DTOs\SendMessageDTO;
use App\Http\Controllers\Controller;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\MessageService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    //

    public function __construct(
        private MessageService $messageService

    ) {
    }

    public function index($chatId, Request $request)
    {
        $user = $request->user();
        return response()->json(
            $this->messageService->getMessages($chatId, $user->id)
        );
    }

    public function memberChat()
    {

    }

    // public function store(Request $request)
    // {
    //     $dto = CreateMessageDTO::fromRequest($request);

    //     $message = $this->messageService->sendMessage($dto);
    //     dd($message);
    //     return response()->json([
    //         'status' => true,

    //         'message' => $message['data']
    //     ]);
    // }




    public function store(Request $request)
    {

        $validated = $request->validate([
            'chat_id' => ['required', 'exists:chats,id'],
            'message' => ['nullable', 'string'],
            'type' => ['required', 'in:text,image,file,system'],
            'reply_to_id' => ['nullable', 'exists:message_chats,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        // 🔐 NEVER trust client
        $validated['sender_id'] = $request->user()->id;

        $user = $request->user();


        $dto = SendMessageDTO::fromArray($validated);

        $message = $this->messageService->sendMessage($dto, $user);

        return response()->json([
            'status' => true,
            'data' => $message
        ], 201);
    }
}
