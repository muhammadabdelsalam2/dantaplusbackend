<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientNoteMention extends Model
{
    protected $fillable = [
        'patient_note_id',
        'user_id',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(PatientNote::class, 'patient_note_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

