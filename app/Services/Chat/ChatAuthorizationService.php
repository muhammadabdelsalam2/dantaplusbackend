<?php

namespace App\Services\Chat;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatAuthorizationService
{
    /**
     * هل المستخدم يقدر يبعت في هذا الـ chat؟
     */
    public function canSend(User $user, Chat $chat, string $action = 'text'): bool
    {
        // 1. Owner دايماً يقدر يبعت
        if ($chat->owner_id === $user->id) {
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

        // 3. لو الـ chat نوعه group، تحقق من الـ role
        if ($chat->type === 'group') {
            // مثلاً: لو عايز تمنع receptionist من بعض الـ channels
            // $role = $user->getRoleNames()->first();
            // if ($role === 'receptionist' && $action === 'file') return false;
        }

        return true;
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
