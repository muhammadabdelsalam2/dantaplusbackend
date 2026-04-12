<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTask extends Model
{
    use HasFactory;
   public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_PICKED_UP,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];
    protected $fillable = [
        'case_id',
        'lab_id',
        'delivery_rep_user_id',
        'status',
        'scheduled_for',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'last_location_lat',
        'last_location_lng',
        'last_location_at',
        'pickup_address',
        'delivery_address',
        'pickup_notes',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_location_at' => 'datetime',
            'last_location_lat' => 'decimal:7',
            'last_location_lng' => 'decimal:7',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function deliveryRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_rep_user_id');
    }
}
