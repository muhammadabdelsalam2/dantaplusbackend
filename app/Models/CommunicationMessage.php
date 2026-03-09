<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationMessage extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_ATTACHMENT = 'attachment';

    public const TYPE_SYSTEM = 'system';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_ATTACHMENT,
        self::TYPE_SYSTEM,
    ];

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_name',
        'sender_type',
        'text',
        'type',
        'related_id',
        'attachment_url',
        'is_system_message',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_system_message' => 'boolean',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CommunicationConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
