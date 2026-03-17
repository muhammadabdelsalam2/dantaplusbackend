<?php

namespace App\Models;

use App\Enums\LabRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'avatar_url',
        'is_active',
        'is_verified',
        'status',
        'role',
        'commission_rates',
        'last_login_at',
        'clinic_id',
        'lab_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'commission_rates' => 'array',
            'role' => LabRole::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function communicationMessages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'sender_id');
    }

    public function deliveryRepProfile(): HasOne
    {
        return $this->hasOne(LabDeliveryRep::class, 'user_id');
    }

    public function assignedCasesAsTechnician(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'assigned_technician_id');
    }

    public function assignedCasesAsDelivery(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'assigned_delivery_id');
    }

    public function createdCases(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'created_by');
    }
}
