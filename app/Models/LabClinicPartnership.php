<?php

namespace App\Models;

use App\Enums\PartnershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabClinicPartnership extends Model
{
    use HasFactory;

    protected $table = 'clinic_lab_partnerships';

    protected $fillable = [
        'lab_id',
        'clinic_id',
        'status',
        'partnership_start_date',
        'total_cases_sent',
        'last_case_date',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PartnershipStatus::class,
            'partnership_start_date' => 'date',
            'last_case_date' => 'date',
            'total_cases_sent' => 'integer',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
