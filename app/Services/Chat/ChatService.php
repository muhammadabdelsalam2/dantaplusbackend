<?php

namespace App\Services\Chat;

use App\Models\Chat;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(
        private ServiceResult $serviceResult
    ) {}

    /**
     * جلب كل الـ chats الخاصة بالمستخدم
     */
    public function getUserChats(int $userId)
    {
        return Chat::accessibleBy($userId)
            ->with([
                'participants:id,name,email',
                'messages' => fn($q) => $q->latest()->limit(1)
            ])
            ->latest()
            ->get();
    }

    /**
     * إنشاء chat جديد مع participants
     */
    public function createChat(array $data): Chat
    {
        return DB::transaction(function () use ($data) {
            $chat = Chat::create([
                'type'        => $data['type'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'team_id'     => $data['team_id'] ?? null,
                'clinic_id'   => $data['clinic_id'] ?? null,
                'owner_id'    => $data['owner_id'],
            ]);

            // إضافة الـ owner كـ participant تلقائياً
            $participants = collect($data['participant_ids'] ?? []);
            $participants->push($data['owner_id']);

            $chat->participants()->syncWithoutDetaching(
                $participants->unique()->toArray()
            );

            return $chat->load('participants:id,name,email');
        });
    }

    /**
     * إضافة مشاركين
     */
    public function addParticipants(int $chatId, array $userIds, $authUser): Chat
    {
        $chat = Chat::findOrFail($chatId);

        // بس الـ owner يقدر يضيف
        if ($chat->owner_id !== $authUser->id) {
            abort(403, 'Only the chat owner can add participants.');
        }

        $chat->participants()->syncWithoutDetaching($userIds);

        return $chat->load('participants:id,name,email');
    }

    /**
     * حذف مشارك
     */
    public function removeParticipant(int $chatId, int $userId, $authUser): Chat
    {
        $chat = Chat::findOrFail($chatId);

        if ($chat->owner_id !== $authUser->id) {
            abort(403, 'Only the chat owner can remove participants.');
        }

        $chat->participants()->detach($userId);

        return $chat->load('participants:id,name,email');
    }

    /**
     * حذف الـ chat
     */
    public function deleteChat(int $chatId, $authUser): bool
    {
        $chat = Chat::findOrFail($chatId);

        if ($chat->owner_id !== $authUser->id) {
            abort(403, 'Only the chat owner can delete this chat.');
        }

        return $chat->delete();
    }
}
