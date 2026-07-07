<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    //
    // Mass assignable attributes
    protected $fillable = [
        'user_id',
        'clinic_id',
        'patient_number',
        'date_of_birth',
        'gender',
        'phone',
        'address',
        'medical_history',
        'allergies',
        'current_medication',
        'insurance_provider',
        'insurance_company_id',
        'insurance_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'patient_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ClinicAppointment::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(ClinicTreatment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ClinicInvoice::class);
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(PatientTooth::class);
    }

    public function radiology(): HasMany
    {
        return $this->hasMany(PatientRadiology::class);
    }

    public function profileNotes(): HasMany
    {
        return $this->hasMany(PatientNote::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function getAgeAttribute(): ?int
    {
        if ($this->date_of_birth) {
            return now()->diffInYears($this->date_of_birth);
        }
        return null;
    }

    /**
     * Scope to filter by gender
     */
    public function scopeGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

}
