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
        'teeth',
        'record_date',
        'linked_appointment_id',
        'linked_treatment_id',
        'notes',
        'file_path',
        'status',
        'before_image_path',
        'after_image_path',
        'report_reference_code',
        'report_format',
        'report_case_selection',
        'report_findings',
        'report_diagnosis',
        'report_generated_by',
        'report_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'report_case_selection' => 'array',
            'report_generated_at' => 'datetime',
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

    public function linkedAppointment(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointment::class, 'linked_appointment_id');
    }

    public function linkedTreatment(): BelongsTo
    {
        return $this->belongsTo(ClinicTreatment::class, 'linked_treatment_id');
    }

    public function reportDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_generated_by');
    }
}
