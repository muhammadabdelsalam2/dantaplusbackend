<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientRadiology extends Model
{
    use HasFactory;

    protected $table = 'patient_radiology';

    protected $fillable = [
        'patient_id',
        'clinic_id',
        'modality',
        'notes',
        'file_path',
        'status',
          'before_image_path',
    'after_image_path',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
