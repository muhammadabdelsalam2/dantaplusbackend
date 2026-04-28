<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicTask extends Model
{
    use HasFactory;

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const STATUSES = ['todo', 'in_progress', 'done'];

    protected $fillable = [
        'clinic_id',
        'title',
        'description',
        'assign_to_user_id',
        'assign_to_doctor_id',
        'priority',
        'status',
        'due_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to_user_id');
    }

    public function assigneeDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'assign_to_doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
