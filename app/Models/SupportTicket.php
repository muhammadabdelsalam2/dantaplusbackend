<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'Open';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_CLOSED = 'Closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITY_LOW = 'Low';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_HIGH = 'High';
    public const PRIORITY_URGENT = 'Urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    public const REPORTER_TYPE_CLINIC = 'clinic';
    public const REPORTER_TYPE_LAB = 'lab';
    public const REPORTER_TYPE_PATIENT = 'patient';
    public const REPORTER_TYPE_COMPANY = 'company';

    public const REPORTER_TYPES = [
        self::REPORTER_TYPE_CLINIC,
        self::REPORTER_TYPE_LAB,
        self::REPORTER_TYPE_PATIENT,
        self::REPORTER_TYPE_COMPANY,
    ];

    protected $fillable = [
        'code',
        'reporter_type',
        'reporter_id',
        'clinic_id',
        'lab_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'assigned_to',
        'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
        ];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportReply::class, 'support_ticket_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
