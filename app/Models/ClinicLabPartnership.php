<?php

namespace App\Models;

use App\Enums\PartnershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicLabPartnership extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_PAUSED = 'Paused';
    public const STATUS_ENDED = 'Ended';

    protected $fillable = [
        'clinic_id',
        'lab_id',
        'status',
        'partnership_start_date',
        'total_cases_sent',
        'last_case_date',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'total_cases_sent' => 'integer',
            'partnership_start_date' => 'date',
            'last_case_date' => 'date',
            'status' => PartnershipStatus::class,
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
