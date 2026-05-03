<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {

    return DB::table('chat_participants')
        ->where('chat_id', $chatId)
        ->where('user_id', $user->id)
        ->exists()

        ||

        DB::table('chats')
            ->where('id', $chatId)
            ->whereExists(function ($q) use ($user) {
                $q->select(DB::raw(1))
                    ->from('teams')
                    ->whereColumn('teams.id', 'chats.team_id')
                    ->where('teams.owner_id', $user->id);
            })
            ->exists();
});

Broadcast::channel('chat.{id}', function ($user, $id) {
    return true;
});

Broadcast::channel('notifications.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
