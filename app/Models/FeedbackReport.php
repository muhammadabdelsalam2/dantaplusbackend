<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'clinic_id',
        'patient_id',
        'rating',
        'comment',
        'allow_testimonial',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_testimonial' => 'boolean',
            'submitted_at' => 'datetime',
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
        return $this->belongsTo(Appointment::class);
    }
}
