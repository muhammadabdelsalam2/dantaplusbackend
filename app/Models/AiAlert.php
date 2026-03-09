<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAlert extends Model
{
    use HasFactory;

    public const SEVERITY_LOW = 'Low';

    public const SEVERITY_MEDIUM = 'Medium';

    public const SEVERITY_HIGH = 'High';

    public const SEVERITY_CRITICAL = 'Critical';

    public const SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
        self::SEVERITY_CRITICAL,
    ];

    protected $fillable = [
        'type',
        'title',
        'message',
        'severity',
        'company_id',
        'maintenance_request_id',
        'is_reviewed',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCompany::class, 'company_id');
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(OwnerMaintenanceRequest::class, 'maintenance_request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
