<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function dentalLabs(): BelongsToMany
    {
        return $this->belongsToMany(DentalLab::class, 'clinic_lab_partnerships', 'clinic_id', 'lab_id')
            ->withPivot(['status', 'total_cases_sent'])
            ->withTimestamps();
    }
}
