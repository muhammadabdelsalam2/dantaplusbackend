<?php
namespace App\Scopes;

class MessageChat
{

    // App\Models\MessageChat.php

    public function scopeForTeamOwner($query, $ownerId)
    {
        return $query->whereHas('chat.team', function ($q) use ($ownerId) {
            $q->where('owner_id', $ownerId);
        });
    }

}