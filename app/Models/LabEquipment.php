<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabEquipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lab_equipments';

    public const STATUS_OPERATIONAL = 'Operational';
    public const STATUS_UNDER_MAINTENANCE = 'Under Maintenance';
    public const STATUS_OUT_OF_SERVICE = 'Out of Service';

    public const STATUSES = [
        self::STATUS_OPERATIONAL,
        self::STATUS_UNDER_MAINTENANCE,
        self::STATUS_OUT_OF_SERVICE,
    ];

    public const MAINTENANCE_STATUS_UP_TO_DATE = 'Up to Date';
    public const MAINTENANCE_STATUS_DUE_SOON = 'Due Soon';
    public const MAINTENANCE_STATUS_OVERDUE = 'Overdue';

    public const MAINTENANCE_STATUSES = [
        self::MAINTENANCE_STATUS_UP_TO_DATE,
        self::MAINTENANCE_STATUS_DUE_SOON,
        self::MAINTENANCE_STATUS_OVERDUE,
    ];

    protected $fillable = [
        'lab_id',
        'name',
        'model_serial_number',
        'purchase_date',
        'last_maintenance_date',
        'maintenance_cycle_days',
        'status',
        'maintenance_notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'last_maintenance_date' => 'date',
            'maintenance_cycle_days' => 'integer',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
