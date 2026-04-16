<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends CommunicationConversation
{
    use HasFactory, BelongsToCompany;

    protected $table = 'communication_conversations';

    protected $fillable = ['company_id', 'clinic_id', 'context_type', 'context_id', 'status', 'last_message_text', 'last_message_at', 'last_message_sender_id'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function clinic(): BelongsTo { return $this->belongsTo(Clinic::class); }
    public function messages(): HasMany { return $this->hasMany(Message::class, 'conversation_id'); }
    public function files(): HasMany { return $this->hasMany(SharedFile::class, 'conversation_id'); }
}
