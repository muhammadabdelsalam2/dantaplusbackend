<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
     protected $table = 'equipments';
    use HasFactory;

    public const STATUS_OPERATIONAL = 'operational';
    public const STATUS_BROKEN = 'broken';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';

    public const STATUSES = [
        self::STATUS_OPERATIONAL,
        self::STATUS_BROKEN,
        self::STATUS_UNDER_MAINTENANCE,
    ];

    protected $fillable = [
        'name',
        'clinic_id',
        'status',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(OwnerMaintenanceRequest::class, 'equipment_id');
    }
}
