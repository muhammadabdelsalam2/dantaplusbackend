<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends MaterialOrderItem
{
    use HasFactory;

    protected $table = 'material_order_items';

    protected $fillable = ['order_id', 'product_id', 'item_name', 'unit', 'quantity', 'unit_price', 'line_total'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class, 'order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class, 'product_id'); }
}
