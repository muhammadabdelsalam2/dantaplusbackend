<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicAppointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'doctor_user_id',
        'patient_name',
        'patient_phone',
        'service_name',
        'appointment_at',
        'duration_minutes',
        'duration',
        'branch_id',
        'branch',
        'room',
        'room_id',
        'payment_type',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
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

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function roomModel(): BelongsTo
{
    return $this->belongsTo(Room::class, 'room_id');
}
    public function invoices(): HasMany
    {
        return $this->hasMany(ClinicInvoice::class, 'appointment_id');
    }
}
