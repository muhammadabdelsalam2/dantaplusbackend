<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'item_name',
        'unit',
        'quantity',
        'qty_original',
        'qty_modified',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'qty_original' => 'integer',
            'qty_modified' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MaterialOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MaterialProduct::class, 'product_id');
    }
}
