<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientNoteAttachment extends Model
{
    protected $fillable = [
        'patient_note_id',
        'file_path',
        'file_name',
        'mime_type',
        'size',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(PatientNote::class, 'patient_note_id');
    }
}

