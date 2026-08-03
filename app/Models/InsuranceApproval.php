<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceApproval extends Model
{
    protected $fillable = [
        'clinic_id',
        'patient_id',
        'insurance_company_id',
        'code',
        'approval_number',
        'ref_id',
        'status',
        'date',
        'expiry_date',
        'coverage_percent',
        'approved_amount',
        'used_amount',
        'documents',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'expiry_date' => 'date',
            'coverage_percent' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'used_amount' => 'decimal:2',
            'documents' => 'array',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(ApprovalService::class);
    }
}
