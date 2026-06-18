<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabInvoiceItemMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_invoice_item_id',
        'lab_material_id',
        'material_name',
        'material_type',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LabInvoiceItem::class, 'lab_invoice_item_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(LabMaterial::class, 'lab_material_id');
    }
}
