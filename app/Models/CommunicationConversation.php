<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationConversation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_ESCALATED = 'Escalated';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_RESOLVED,
        self::STATUS_ESCALATED,
    ];

    protected $fillable = [
        'clinic_id',
        'lab_id',
        'context_type',
        'context_id',
        'status',
        'last_message_text',
        'last_message_at',
        'last_message_sender_id',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function lastMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_sender_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'conversation_id');
    }
}
