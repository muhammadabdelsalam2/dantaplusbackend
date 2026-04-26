<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackLog extends Model
{
    protected $fillable = [
        'clinic_id',
        'patient_id',
        'clinic_appointment_id',
        'channel',
        'message_template',
        'rendered_message',
        'feedback_link',
        'status',
        'scheduled_for',
        'sent_at',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointment::class, 'clinic_appointment_id');
    }
}
