<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorReminderLog extends Model
{
    protected $fillable = [
        'clinic_id',
        'doctor_user_id',
        'channel',
        'message_template',
        'rendered_message',
        'reminder_date',
        'status',
        'triggered_at',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'triggered_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }
}
