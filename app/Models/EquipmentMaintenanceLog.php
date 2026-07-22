<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMaintenanceLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'equipment_id',
        'performed_by',
        'notes',
        'maintenance_date',
        'next_due_date',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_date' => 'date',
            'next_due_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(LabEquipment::class, 'equipment_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
