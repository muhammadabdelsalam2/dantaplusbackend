<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnerMaintenanceRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_OVERDUE = 'Overdue';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'request_code',
        'clinic_id',
        'equipment',
        'issue_description',
        'assigned_company_id',
        'status',
        'created_by',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCompany::class, 'assigned_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AiAlert::class, 'maintenance_request_id');
    }
}
