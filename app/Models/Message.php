<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends CommunicationMessage
{
    use HasFactory, BelongsToCompany;

    protected $table = 'communication_messages';

    protected $fillable = [
        'conversation_id', 'company_id', 'sender_type', 'sender_id', 'sender_name', 'message_type', 'content',
        'related_type', 'related_id', 'attachment_path', 'is_read', 'text', 'type', 'attachment_url', 'is_system_message', 'read_at',
    ];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class, 'conversation_id'); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}
