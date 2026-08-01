<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientTooth extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'clinic_id',
        'tooth_number',
        'status',
        'is_present',
        'target_area',
        'procedure_id',
        'treating_doctor_id',
        'billing_method',
        'clinical_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_present' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'procedure_id');
    }

    public function treatingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treating_doctor_id');
    }
}
