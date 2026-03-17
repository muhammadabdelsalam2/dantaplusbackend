<?php

namespace App\Models;

use App\Enums\ClinicType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clinic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'owner_name',
        'email',
        'phone',
        'address',
        'subdomain',
        'clinic_type',
        'is_external',
        'notes',
        'added_by',
        'registration_date',
        'subscription_plan',
        'payment_method',
        'status',
        'start_date',
        'expiry_date',
        'max_users',
        'max_branches',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'expiry_date' => 'datetime',
            'registration_date' => 'date',
            'is_external' => 'boolean',
            'clinic_type' => ClinicType::class,
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ClinicModule::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function labPartnerships(): HasMany
    {
        return $this->hasMany(ClinicLabPartnership::class);
    }

    public function partneredLabs(): BelongsToMany
    {
        return $this->belongsToMany(DentalLab::class, 'clinic_lab_partnerships', 'clinic_id', 'lab_id')
            ->withPivot(['status', 'total_cases_sent', 'partnership_start_date', 'last_case_date', 'invited_by'])
            ->withTimestamps();
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function dentalLabs(): BelongsToMany
    {
        return $this->belongsToMany(DentalLab::class, 'clinic_lab_partnerships', 'clinic_id', 'lab_id')
            ->withPivot(['status', 'total_cases_sent'])
            ->withTimestamps();
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(OwnerMaintenanceRequest::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(CommunicationConversation::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseModel::class);
    }
}
