<?php

namespace App\Services\Chat;

use App\Models\Chat;
use App\Models\TeamRolePermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatAuthorizationService
{

    public function canSend(User $user, Chat $chat, string $type): bool
    {
        $role = $user->getRoleNames()->first();

        // 1. global block example
        if ($role === 'Admin' && $type === 'send_text_blocked') {
            return false;
        }

        // 2. map permission
        $permission = match ($type) {
            'text' => 'send_text',
            'voice' => 'send_voice',
            'file' => 'send_file',
            default => null,
        };

        if (!$permission) {
            return false;
        }

        return $this->roleHasPermission($role, $permission);
    }

    private function roleHasPermission($role, $permission): bool
    {
        return DB::table('team_role_permissions')
            ->where('role', $role)
            ->where('permission', $permission)
            ->exists();
    }


}