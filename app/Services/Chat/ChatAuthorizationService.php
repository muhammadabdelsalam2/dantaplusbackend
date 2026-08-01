<?php

namespace App\Services\Chat;

use App\Models\Chat;
use App\Models\User;
use App\Services\Clinic\Settings\CommunicationPermissionService;
use Illuminate\Support\Facades\DB;

class ChatAuthorizationService
{
    /**
     * هل المستخدم يقدر يبعت في هذا الـ chat؟
     */
    public function canSend(User $user, Chat $chat, string $action = 'text'): bool
    {
        // 1. Owner دايماً يقدر يبعت
        if ($chat->owner_id === $user->id && $action === 'delete') {
            return true;
        }

        // 2. تحقق إن المستخدم participant
        $isParticipant = DB::table('chat_participants')
            ->where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isParticipant) {
            return false;
        }

        $permissionService = app(CommunicationPermissionService::class);
        if ($action === 'voice') {
            return $permissionService->allows($user, 'can_send_voice_notes');
        }
        if ($action === 'delete') {
            return $permissionService->allows($user, 'can_delete_messages');
        }

        return $permissionService->allows($user, 'can_send_notes');
    }

    /**
     * هل المستخدم يقدر يشوف هذا الـ chat؟
     */
    public function canView(User $user, Chat $chat): bool
    {
        if ($chat->owner_id === $user->id) {
            return true;
        }

        return DB::table('chat_participants')
            ->where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
