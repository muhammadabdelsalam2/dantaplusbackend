<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_CONFIRMED = 'Confirmed';
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_SHIPPED = 'Shipped';
    public const STATUS_DELIVERED = 'Delivered';
    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'order_code',
        'clinic_id',
        'supplier_company_id',
        'order_date',
        'amount_total',
        'status',
        'commission_amount',
        'notes',
        'payment_method',
        'payment_status',
        'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'amount_total' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(MaterialCompany::class, 'supplier_company_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialOrderItem::class, 'order_id');
    }
}
