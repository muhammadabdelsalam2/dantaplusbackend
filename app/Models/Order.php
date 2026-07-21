<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends MaterialOrder
{
    use HasFactory, BelongsToCompany;

    protected $table = 'material_orders';

   protected $fillable = [
    'company_id', 'clinic_id', 'external_clinic_name', 'external_clinic_phone', 'status', 'notes', 'total_amount',
    'payment_method', 'payment_status', 'source', 'delivery_address', 'delivery_at', 'created_by',
    'order_code', 'supplier_company_id', 'order_date', 'amount_total', 'commission_amount', 'payment_reference',
    'supplier_note', 'modified_by_supplier', 'shipping_cost',
];

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
    public function clinic(): BelongsTo { return $this->belongsTo(Clinic::class, 'clinic_id'); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class, 'order_id'); }
    public function invoice() { return $this->hasOne(Invoice::class, 'order_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function statusHistories(): HasMany { return $this->hasMany(OrderStatusHistory::class, 'order_id'); }
}
