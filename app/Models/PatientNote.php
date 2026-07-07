<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'user_id',
        'clinic_id',
        'note',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PatientNoteAttachment::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(PatientNoteMention::class);
    }
}
