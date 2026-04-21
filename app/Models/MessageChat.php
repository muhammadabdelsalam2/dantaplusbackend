<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageChat extends Model
{
    //
    protected $fillable = [
        'chat_id',
        'sender_id',

        // content
        'message',
        'type',

        // message state
        'is_edited',
        'edited_at',
        'is_deleted',
        'deleted_at',
        // reply system
        'reply_to_id',
        // metadata
        'metadata', // JSON (important)
    ];
    // protected $fillable = [
    //     'chat_id',
    //     'sender_id',
    //     'message',
    //     'type',
    //     'reply_to_id',
    //     'is_edited',
    //     'edited_at',
    //     'is_deleted',
    //     'deleted_at',
    //     'metadata',
    // ];

    protected $casts = [
        'metadata' => 'array',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reads()
    {
        return $this->hasMany(MessageRead::class);
    }

    public function mentions()
    {
        return $this->hasMany(MessageMention::class);
    }

    public function replies()
    {
        return $this->hasMany(MessageChat::class, 'reply_to_id');
    }

    public function parent()
    {
        return $this->belongsTo(MessageChat::class, 'reply_to_id');
    }
    public function scopeForTeamOwner($query, $ownerId)
    {
        return $query->whereHas('chat.team', function ($q) use ($ownerId) {
            $q->where('owner_id', $ownerId);
        });
    }
}
