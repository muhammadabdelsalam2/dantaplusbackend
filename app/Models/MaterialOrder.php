<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING_SUPPLIER_CONFIRMATION = OrderStatus::PENDING_SUPPLIER_CONFIRMATION;
    public const STATUS_PENDING = OrderStatus::PENDING_SUPPLIER_CONFIRMATION;
    public const STATUS_ACCEPTED = OrderStatus::ACCEPTED;
    public const STATUS_CONFIRMED = OrderStatus::ACCEPTED;
    public const STATUS_PROCESSING = OrderStatus::PROCESSING;
    public const STATUS_SHIPPED = OrderStatus::SHIPPED;
    public const STATUS_DELIVERED = OrderStatus::DELIVERED;
    public const STATUS_COMPLETED = OrderStatus::COMPLETED;
    public const STATUS_CANCELLED = OrderStatus::CANCELLED;
    public const STATUS_REJECTED = OrderStatus::REJECTED;

    public const STATUSES = OrderStatus::ALL;

    protected $fillable = [
        'order_code',
        'clinic_id',
        'supplier_company_id',
        'company_id',
        'order_date',
        'amount_total',
        'total_amount',
        'status',
        'commission_amount',
        'notes',
        'supplier_note',
        'modified_by_supplier',
        'payment_method',
        'payment_status',
        'payment_reference',
        'created_by',
        'delivery_address',
        'delivery_at',
        'external_clinic_name',
        'external_clinic_phone',
        'shipping_cost',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'amount_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'modified_by_supplier' => 'boolean',
            'delivery_at' => 'datetime',
            'shipping_cost' => 'decimal:2',
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
