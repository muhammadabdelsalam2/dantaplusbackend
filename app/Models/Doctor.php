<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'specialization',
        'license_number',
        'working_hours_from',
        'working_hours_to',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'dentist_id');
    }

    public function clinicTasks(): HasMany
    {
        return $this->hasMany(ClinicTask::class, 'assign_to_doctor_id');
    }
}
