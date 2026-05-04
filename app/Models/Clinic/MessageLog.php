<?php

namespace App\Models\Clinic;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    protected $table = 'message_logs';

    protected $fillable = [
        'message_id',
        'clinic_id',
        'patient_id',
        'appointment_id',
        'doctor_user_id',
        'template_id',
        'sent_by',
        'batch_uuid',
        'type',
        'status',
        'message_body',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
