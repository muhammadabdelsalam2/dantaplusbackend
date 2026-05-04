<?php

namespace App\Models;

use App\Models\MaterialCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationConversation extends Model
{
    use HasFactory;

    public const CONTACT_TYPE_LAB = 'lab';
    public const CONTACT_TYPE_SUPPLIER = 'supplier';

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_ESCALATED = 'Escalated';

    public const CONTACT_TYPES = [
        self::CONTACT_TYPE_LAB,
        self::CONTACT_TYPE_SUPPLIER,
    ];

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_RESOLVED,
        self::STATUS_ESCALATED,
    ];

    protected $fillable = [
        'clinic_id',
        'lab_id',
        'company_id',
        'contact_type',
        'contact_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MaterialCompany::class, 'company_id');
    }

    public function lastMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_sender_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(CommunicationMessage::class, 'conversation_id')->latestOfMany();
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'context_id');
    }
}
