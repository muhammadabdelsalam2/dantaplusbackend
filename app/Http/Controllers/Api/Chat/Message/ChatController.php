<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService
    ) {}

    /**
     * جلب كل الـ chats الخاصة بالمستخدم
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $result = $this->chatService->getUserChats($user->id);

        return response()->json([
            'status' => true,
            'data' => $result
        ]);
    }

    /**
     * إنشاء group chat جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'type'           => ['required', 'in:direct,group'],
            'team_id'        => ['nullable', 'exists:teams,id'],
            'clinic_id'      => ['nullable', 'exists:clinics,id'],
            'participant_ids'=> ['nullable', 'array'],
            'participant_ids.*' => ['exists:users,id'],
        ]);

        $validated['owner_id'] = $request->user()->id;

        $result = $this->chatService->createChat($validated);

        return response()->json([
            'status' => true,
            'data'   => $result
        ], 201);
    }

    /**
     * إضافة مشاركين للـ chat
     */
    public function addParticipants(Request $request, int $chatId)
    {
        $validated = $request->validate([
            'user_ids'   => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $result = $this->chatService->addParticipants(
            $chatId,
            $validated['user_ids'],
            $request->user()
        );

        return response()->json([
            'status' => true,
            'data'   => $result
        ]);
    }

    /**
     * حذف مشارك من الـ chat
     */
    public function removeParticipant(Request $request, int $chatId, int $userId)
    {
        $result = $this->chatService->removeParticipant(
            $chatId,
            $userId,
            $request->user()
        );

        return response()->json([
            'status' => true,
            'data'   => $result
        ]);
    }

    /**
     * حذف الـ chat
     */
    public function destroy(Request $request, int $chatId)
    {
        $result = $this->chatService->deleteChat($chatId, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Chat deleted successfully'
        ]);
    }
}
