<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ORDERED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'clinic_id',
        'material_id',
        'supplier_id',
        'supplier_name',
        'qty',
        'unit_cost',
        'total_cost',
        'status',
        'po_number',
        'notes',
        'ordered_at',
        'received_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialProduct::class, 'material_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MaterialCompany::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
